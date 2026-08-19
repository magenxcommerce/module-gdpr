<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\Cron;

use Magenx\Gdpr\Model\Anonymizer;
use Magenx\Gdpr\Model\Config;
use Magenx\Gdpr\Model\RetentionCutoff;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\FlagManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Off by default (magenx_gdpr/old_orders/enabled). Anonymizes the
 * billing/shipping address PII on finished orders older than the configured
 * window. Totals, items and financial history are never touched. Processes up
 * to 200 matching orders per run so a large backlog is worked off gradually by
 * the daily schedule rather than in one very long run.
 *
 * Progress is tracked with a persisted cursor holding the highest order id
 * already processed, and each run selects only ids above it. Without a cursor
 * the run would re-select the same oldest 200 orders every night - an
 * already-anonymized order still matches "created before the cutoff", so the
 * backlog behind those 200 would never be reached. A monotonically increasing
 * cursor is safe here because orders are created in id order, so any order that
 * later ages past the cutoff necessarily has a higher id than everything
 * already processed.
 *
 * Only complete/closed/canceled orders are eligible: an old order still being
 * processed or on hold needs its shipping address to be fulfilled.
 */
class AnonymizeOldOrders
{
    private const BATCH_SIZE = 200;

    /** Flag holding the highest sales_order entity_id already anonymized. */
    private const CURSOR_FLAG_CODE = 'magenx_gdpr_old_orders_cursor';

    private const FINISHED_ORDER_STATUSES = ['complete', 'closed', 'canceled'];

    public function __construct(
        private readonly Config $config,
        private readonly Anonymizer $anonymizer,
        private readonly RetentionCutoff $retentionCutoff,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly FlagManager $flagManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isOldOrderAnonymizationEnabled()) {
            return;
        }

        $days = $this->config->getOldOrderDays();
        if ($days <= 0) {
            return;
        }

        $cutoff = $this->retentionCutoff->since($days);
        $cursor = (int) $this->flagManager->getFlagData(self::CURSOR_FLAG_CODE);

        $sortOrder = $this->sortOrderBuilder->setField('entity_id')->setAscendingDirection()->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('created_at', $cutoff, 'lt')
            ->addFilter('entity_id', $cursor, 'gt')
            ->addFilter('status', self::FINISHED_ORDER_STATUSES, 'in')
            ->addSortOrder($sortOrder)
            ->create();
        $criteria->setPageSize(self::BATCH_SIZE);
        $criteria->setCurrentPage(1);

        $highestProcessed = $cursor;

        foreach ($this->orderRepository->getList($criteria)->getItems() as $order) {
            $orderId = (int) $order->getEntityId();

            try {
                $this->anonymizer->anonymizeOrderAddresses($order);
                $highestProcessed = max($highestProcessed, $orderId);
            } catch (\Throwable $e) {
                // Stop at the first failure rather than skipping past it: the
                // cursor may only advance over orders that were actually
                // anonymized, or the skipped one is never revisited.
                $this->logger->error(
                    sprintf('Magenx_Gdpr: failed to anonymize order %d: %s', $orderId, $e->getMessage()),
                    ['exception' => $e]
                );
                break;
            }
        }

        if ($highestProcessed > $cursor) {
            $this->flagManager->saveFlag(self::CURSOR_FLAG_CODE, $highestProcessed);
        }
    }
}
