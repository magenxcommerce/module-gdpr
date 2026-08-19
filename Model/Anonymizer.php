<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * The one place customer PII actually gets scrubbed. Used identically by the
 * self-service "anonymize my data" mutation and by the admin-approved erase
 * flow - the two data-subject-request types differ only in whether a human
 * has to approve first, not in what happens to the data. There is no
 * separate "delete the account" capability: Magento customer/order records
 * cannot be hard-deleted without breaking order history, so anonymization is
 * the erasure mechanism.
 */
class Anonymizer
{
    /** Order statuses that block anonymization - the order isn't finished yet. */
    private const OPEN_ORDER_STATUSES = ['pending', 'processing', 'pending_payment', 'payment_review', 'holded'];

    private const ANONYMIZED_LABEL = 'Anonymized';

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function hasOpenOrders(int $customerId): bool
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addFilter('status', self::OPEN_ORDER_STATUSES, 'in')
            ->create();

        return $this->orderRepository->getList($criteria)->getTotalCount() > 0;
    }

    /**
     * Replaces the customer's name/email/dob and every address's PII with
     * anonymized placeholders. The customer id and order history are kept -
     * only who the records identify is erased.
     */
    public function anonymizeCustomer(int $customerId): void
    {
        $customer = $this->customerRepository->getById($customerId);
        $customer->setFirstname(self::ANONYMIZED_LABEL);
        $customer->setLastname(self::ANONYMIZED_LABEL);
        $customer->setEmail(sprintf('anonymized-customer-%d@invalid.example', $customerId));
        $customer->setDob(null);
        $customer->setGender(null);

        $addresses = $customer->getAddresses() ?? [];
        foreach ($addresses as $address) {
            $address->setFirstname(self::ANONYMIZED_LABEL);
            $address->setLastname(self::ANONYMIZED_LABEL);
            $address->setStreet([self::ANONYMIZED_LABEL]);
            $address->setTelephone('000000000');
            $address->setCompany(null);
            $address->setFax(null);
        }
        $customer->setAddresses($addresses);

        $this->customerRepository->save($customer);
    }

    /**
     * Anonymizes the PII on one order's billing/shipping addresses. Totals,
     * items and financial history are left untouched - those are the
     * merchant's own accounting records, not the customer's personal data.
     */
    public function anonymizeOrderAddresses(\Magento\Sales\Api\Data\OrderInterface $order): void
    {
        foreach (array_filter([$order->getBillingAddress(), $order->getShippingAddress()]) as $address) {
            /** @var OrderAddressInterface $address */
            $address->setFirstname(self::ANONYMIZED_LABEL);
            $address->setLastname(self::ANONYMIZED_LABEL);
            $address->setStreet(self::ANONYMIZED_LABEL);
            $address->setTelephone('000000000');
            $address->setCompany(null);
            $address->setFax(null);
        }

        $this->orderRepository->save($order);
    }
}
