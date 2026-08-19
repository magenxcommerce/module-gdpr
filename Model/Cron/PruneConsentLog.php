<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\Cron;

use Magenx\Gdpr\Model\Config;
use Magenx\Gdpr\Model\RetentionCutoff;
use Magento\Framework\App\ResourceConnection;

/**
 * Always runs (not gated on the module's own enabled flag) - it only ever
 * deletes rows past the configured retention window, so there is no reason
 * to make it opt-in the way the two anonymization crons are.
 *
 * Deletes in chunks rather than as one statement: on a store that has been
 * logging consent for years, a single unbounded DELETE holds a lock over the
 * whole table for as long as it takes, during which every cookie-banner
 * submission blocks behind it.
 */
class PruneConsentLog
{
    private const DELETE_CHUNK_SIZE = 10000;

    /** Ceiling on chunks per run, so one pass cannot become an unbounded job. */
    private const MAX_CHUNKS_PER_RUN = 100;

    public function __construct(
        private readonly Config $config,
        private readonly RetentionCutoff $retentionCutoff,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function execute(): void
    {
        $days = $this->config->getConsentLogRetentionDays();
        if ($days <= 0) {
            return;
        }

        $cutoff = $this->retentionCutoff->since($days);
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('magenx_gdpr_consent_log');

        $sql = sprintf(
            'DELETE FROM %s WHERE created_at < ? LIMIT %d',
            $connection->quoteIdentifier($table),
            self::DELETE_CHUNK_SIZE
        );

        for ($chunk = 0; $chunk < self::MAX_CHUNKS_PER_RUN; $chunk++) {
            $deleted = $connection->query($sql, [$cutoff])->rowCount();
            if ($deleted < self::DELETE_CHUNK_SIZE) {
                break;
            }
        }
    }
}
