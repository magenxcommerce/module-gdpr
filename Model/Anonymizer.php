<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * The one place customer PII actually gets scrubbed. Used identically by the
 * self-service "anonymize my data" mutation and by the admin-approved erase
 * flow - the two data-subject-request types differ only in whether a human
 * has to approve first, not in what happens to the data. There is no
 * separate "delete the account" capability: Magento customer/order records
 * cannot be hard-deleted without breaking order history, so anonymization is
 * the erasure mechanism.
 *
 * Anonymizing a customer covers every place their identity is stored, not
 * just customer_entity: the denormalized customer_* columns on sales_order,
 * the billing/shipping rows in sales_order_address, the flat sales_order_grid
 * the admin actually looks at, and their newsletter subscription. Leaving any
 * of those behind would make the erase flow a no-op in practice - the name,
 * street and email would still be one order view away.
 *
 * Region is deliberately left alone in both paths: a state/province is too
 * coarse to identify anyone, and Magento's customer AddressInterface::setRegion
 * takes a RegionInterface rather than null, so blanking it cleanly is not
 * possible without inventing an empty region object.
 */
class Anonymizer
{
    /** Order statuses that block anonymization - the order isn't finished yet. */
    private const OPEN_ORDER_STATUSES = ['pending', 'processing', 'pending_payment', 'payment_review', 'holded'];

    public const ANONYMIZED_LABEL = 'Anonymized';

    /**
     * RFC 6761 reserves .example for documentation, so an address in this
     * domain can never be delivered to and can never collide with a real
     * customer's. It is also how the dormant-account cron recognises a row it
     * has already processed.
     */
    public const ANONYMIZED_EMAIL_DOMAIN = 'invalid.example';

    private const ANONYMIZED_PHONE = '000000000';

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderAddressRepositoryInterface $orderAddressRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly CustomerTokenServiceInterface $customerTokenService,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /** The placeholder address a given customer id is anonymized to. */
    public static function anonymizedEmail(int $customerId): string
    {
        return sprintf('anonymized-customer-%d@%s', $customerId, self::ANONYMIZED_EMAIL_DOMAIN);
    }

    public function hasOpenOrders(int $customerId): bool
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addFilter('status', self::OPEN_ORDER_STATUSES, 'in')
            ->create();
        // Only getTotalCount() is read, so there is no reason to hydrate a page
        // of order entities to answer a yes/no question.
        $criteria->setPageSize(1);

        return $this->orderRepository->getList($criteria)->getTotalCount() > 0;
    }

    /**
     * Replaces the customer's name/email/dob and every address's PII with
     * anonymized placeholders, then does the same to their order history,
     * newsletter subscription and any live API tokens. The customer id and the
     * orders themselves are kept - only who the records identify is erased.
     *
     * The steps span several repositories and cannot share one transaction, so
     * a failure part way through leaves a partially anonymized customer. That
     * is recoverable rather than corrupting: every step writes fixed
     * placeholders derived from the customer id, so the whole method is
     * idempotent and re-running it finishes the job. Callers keep the request
     * row out of a "done" state until this returns, so a retry is always
     * reachable.
     */
    public function anonymizeCustomer(int $customerId): void
    {
        $email = self::anonymizedEmail($customerId);

        $customer = $this->customerRepository->getById($customerId);
        $customer->setFirstname(self::ANONYMIZED_LABEL);
        $customer->setMiddlename(null);
        $customer->setLastname(self::ANONYMIZED_LABEL);
        $customer->setEmail($email);
        $customer->setDob(null);
        $customer->setGender(null);
        $customer->setTaxvat(null);

        $addresses = $customer->getAddresses() ?? [];
        foreach ($addresses as $address) {
            $address->setFirstname(self::ANONYMIZED_LABEL);
            $address->setMiddlename(null);
            $address->setLastname(self::ANONYMIZED_LABEL);
            $address->setStreet([self::ANONYMIZED_LABEL]);
            $address->setCity(self::ANONYMIZED_LABEL);
            $address->setPostcode(self::ANONYMIZED_LABEL);
            $address->setTelephone(self::ANONYMIZED_PHONE);
            $address->setCompany(null);
            $address->setFax(null);
            $address->setVatId(null);
        }
        $customer->setAddresses($addresses);

        $this->customerRepository->save($customer);

        $this->anonymizeCustomerOrders($customerId);
        $this->anonymizeNewsletterSubscription($customerId, $email);
        $this->revokeAccessTokens($customerId);
    }

    /**
     * Anonymizes the PII on one order: the billing/shipping addresses and the
     * customer name/email columns denormalized onto the order and its grid row.
     * Totals, items and financial history are left untouched - those are the
     * merchant's own accounting records, not the customer's personal data.
     *
     * Saves the addresses through OrderAddressRepositoryInterface and updates
     * the order's own columns directly rather than calling
     * OrderRepositoryInterface::save(): a full order save fires every order
     * observer and plugin in the installation and re-writes the grid row, which
     * is far too much work to repeat for each of a cron batch's 200 orders.
     */
    public function anonymizeOrderAddresses(OrderInterface $order): void
    {
        $orderId = (int) $order->getEntityId();
        $email = $order->getCustomerId()
            ? self::anonymizedEmail((int) $order->getCustomerId())
            : sprintf('anonymized-order-%d@%s', $orderId, self::ANONYMIZED_EMAIL_DOMAIN);

        foreach (array_filter([$order->getBillingAddress(), $order->getShippingAddress()]) as $address) {
            /** @var OrderAddressInterface $address */
            $address->setFirstname(self::ANONYMIZED_LABEL);
            $address->setMiddlename(null);
            $address->setLastname(self::ANONYMIZED_LABEL);
            $address->setStreet(self::ANONYMIZED_LABEL);
            $address->setCity(self::ANONYMIZED_LABEL);
            $address->setPostcode(self::ANONYMIZED_LABEL);
            $address->setTelephone(self::ANONYMIZED_PHONE);
            $address->setEmail($email);
            $address->setCompany(null);
            $address->setFax(null);
            $address->setVatId(null);
            $this->orderAddressRepository->save($address);
        }

        $this->anonymizeOrderColumns($orderId, $email);
    }

    /**
     * Runs every order the customer placed through anonymizeOrderAddresses.
     * Paged rather than fetched in one go: a long-standing customer can have
     * thousands of orders, and each page is released before the next is loaded.
     *
     * Sorted by entity_id so the pages partition the result set: an unsorted
     * getList has no guaranteed row order between calls, which for a customer
     * with more than one page means an order can appear on two pages and
     * another on none - and the one on none keeps its PII.
     */
    private function anonymizeCustomerOrders(int $customerId): void
    {
        $pageSize = 100;
        $page = 1;
        $sortOrder = $this->sortOrderBuilder->setField('entity_id')->setAscendingDirection()->create();

        do {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('customer_id', $customerId)
                ->addSortOrder($sortOrder)
                ->create();
            $criteria->setPageSize($pageSize);
            $criteria->setCurrentPage($page);

            $orders = $this->orderRepository->getList($criteria)->getItems();
            foreach ($orders as $order) {
                $this->anonymizeOrderAddresses($order);
            }
            $page++;
        } while (count($orders) === $pageSize);
    }

    /** Blanks the customer identity columns denormalized onto the order and its grid row. */
    private function anonymizeOrderColumns(int $orderId, string $email): void
    {
        $connection = $this->resourceConnection->getConnection('sales');

        $connection->update(
            $this->resourceConnection->getTableName('sales_order'),
            [
                'customer_email' => $email,
                'customer_firstname' => self::ANONYMIZED_LABEL,
                'customer_middlename' => null,
                'customer_lastname' => self::ANONYMIZED_LABEL,
                'customer_dob' => null,
                'customer_taxvat' => null,
            ],
            ['entity_id = ?' => $orderId]
        );

        // sales_order_grid is the flat table the admin order grid actually
        // reads. Without this the anonymized name and email are still one
        // grid search away.
        $gridTable = $this->resourceConnection->getTableName('sales_order_grid');
        if ($connection->isTableExists($gridTable)) {
            $connection->update(
                $gridTable,
                [
                    'customer_email' => $email,
                    'customer_name' => self::ANONYMIZED_LABEL,
                    'billing_name' => self::ANONYMIZED_LABEL,
                    'shipping_name' => self::ANONYMIZED_LABEL,
                    'billing_address' => self::ANONYMIZED_LABEL,
                    'shipping_address' => self::ANONYMIZED_LABEL,
                ],
                ['entity_id = ?' => $orderId]
            );
        }
    }

    /**
     * Repoints the customer's newsletter subscription at the anonymized
     * address. The subscription row itself is kept so the unsubscribe state is
     * not silently reset, but it no longer carries a reachable address.
     */
    private function anonymizeNewsletterSubscription(int $customerId, string $email): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('newsletter_subscriber');

        if (!$connection->isTableExists($table)) {
            return;
        }

        $connection->update($table, ['subscriber_email' => $email], ['customer_id = ?' => $customerId]);
    }

    /**
     * Invalidates any customer access token still in flight. Without this an
     * erased customer stays signed in on every device they were signed in on,
     * holding a working token for an account whose data was just erased.
     */
    private function revokeAccessTokens(int $customerId): void
    {
        try {
            $this->customerTokenService->revokeCustomerAccessToken($customerId);
        } catch (LocalizedException $e) {
            // Thrown as "This customer has no tokens" when there was nothing to
            // revoke, which is the common case and not a failure.
            $this->logger->debug(
                sprintf('Magenx_Gdpr: no access tokens to revoke for customer %d (%s)', $customerId, $e->getMessage())
            );
        }
    }
}
