<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\Cron;

use Magenx\Gdpr\Model\Anonymizer;
use Magenx\Gdpr\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Off by default (magenx_gdpr/old_orders/enabled). Anonymizes the
 * billing/shipping address PII on orders older than the configured window.
 * Totals, items and financial history are never touched. Processes up to
 * 200 matching orders per run so a large backlog is worked off gradually by
 * the daily schedule rather than in one very long run.
 */
class AnonymizeOldOrders
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly Config $config,
        private readonly Anonymizer $anonymizer,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isOldOrderAnonymizationEnabled()) {
            return;
        }

        $days = $this->config->getOldOrderDays();
        $cutoff = date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $days)));

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('created_at', $cutoff, 'lt')
            ->create();
        $criteria->setPageSize(self::BATCH_SIZE);
        $criteria->setCurrentPage(1);

        foreach ($this->orderRepository->getList($criteria)->getItems() as $order) {
            $billing = $order->getBillingAddress();
            if ($billing && $billing->getLastname() === 'Anonymized') {
                continue; // already done
            }
            $this->anonymizer->anonymizeOrderAddresses($order);
        }
    }
}
