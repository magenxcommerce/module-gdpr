<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\Cron;

use Magenx\Gdpr\Model\Anonymizer;
use Magenx\Gdpr\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Off by default (magenx_gdpr/dormant_accounts/enabled). Anonymizes accounts
 * created more than the configured number of days ago that have either never
 * ordered or whose most recent order is older than that same window - never
 * touches an account with an order still in progress.
 */
class AnonymizeDormantAccounts
{
    public function __construct(
        private readonly Config $config,
        private readonly Anonymizer $anonymizer,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isDormantAccountAnonymizationEnabled()) {
            return;
        }

        $days = $this->config->getDormantAccountDays();
        $cutoff = date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $days)));

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['c' => $this->resourceConnection->getTableName('customer_entity')], ['entity_id', 'email'])
            ->joinLeft(
                ['o' => $this->resourceConnection->getTableName('sales_order')],
                'o.customer_id = c.entity_id',
                ['last_order_at' => new Expression('MAX(o.created_at)')]
            )
            ->where('c.created_at < ?', $cutoff)
            ->group('c.entity_id')
            ->having('last_order_at IS NULL OR last_order_at < ?', $cutoff);

        foreach ($connection->fetchAll($select) as $row) {
            $customerId = (int) $row['entity_id'];
            if (str_contains((string) $row['email'], '@invalid.example')) {
                continue; // already anonymized
            }
            if ($this->anonymizer->hasOpenOrders($customerId)) {
                continue;
            }
            $this->anonymizer->anonymizeCustomer($customerId);
        }
    }
}
