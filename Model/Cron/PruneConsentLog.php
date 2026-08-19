<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\Cron;

use Magenx\Gdpr\Model\Config;
use Magento\Framework\App\ResourceConnection;

/**
 * Always runs (not gated on the module's own enabled flag) - it only ever
 * deletes rows past the configured retention window, so there is no reason
 * to make it opt-in the way the two anonymization crons are.
 */
class PruneConsentLog
{
    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function execute(): void
    {
        $days = $this->config->getConsentLogRetentionDays();
        if ($days <= 0) {
            return;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $days)));
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName('magenx_gdpr_consent_log'),
            ['created_at < ?' => $cutoff]
        );
    }
}
