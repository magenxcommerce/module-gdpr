<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\Cron;

use Magenx\Gdpr\Model\Anonymizer;
use Magenx\Gdpr\Model\Config;
use Magenx\Gdpr\Model\RetentionCutoff;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Psr\Log\LoggerInterface;

/**
 * Off by default (magenx_gdpr/dormant_accounts/enabled). Anonymizes accounts
 * that have been silent for the configured number of days: no sign-in and no
 * order in that window, and the account itself older than it. Never touches an
 * account with an order still in progress.
 *
 * "Silent" is measured from customer_log.last_login_at (Magento's own login
 * log) and sales_order.created_at, not from customer_entity.created_at alone -
 * an account created three years ago whose owner signs in every week is not
 * dormant, and anonymizing it would destroy an active customer's data.
 *
 * Processes at most one batch per run so a store enabling this for the first
 * time works its backlog off over successive nights instead of loading every
 * matching customer into one request.
 */
class AnonymizeDormantAccounts
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly Config $config,
        private readonly Anonymizer $anonymizer,
        private readonly ResourceConnection $resourceConnection,
        private readonly RetentionCutoff $retentionCutoff,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isDormantAccountAnonymizationEnabled()) {
            return;
        }

        $days = $this->config->getDormantAccountDays();
        if ($days <= 0) {
            return;
        }

        $cutoff = $this->retentionCutoff->since($days);

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['c' => $this->resourceConnection->getTableName('customer_entity')], ['entity_id'])
            ->joinLeft(
                ['o' => $this->resourceConnection->getTableName('sales_order')],
                'o.customer_id = c.entity_id',
                ['last_order_at' => new Expression('MAX(o.created_at)')]
            )
            ->joinLeft(
                ['l' => $this->resourceConnection->getTableName('customer_log')],
                'l.customer_id = c.entity_id',
                ['last_login_at' => new Expression('MAX(l.last_login_at)')]
            )
            ->where('c.created_at < ?', $cutoff)
            // Skip rows a previous run already anonymized, in SQL rather than in
            // PHP, so the batch is spent on accounts that still need the work.
            ->where('c.email NOT LIKE ?', '%@' . Anonymizer::ANONYMIZED_EMAIL_DOMAIN)
            ->group('c.entity_id')
            ->having('last_order_at IS NULL OR last_order_at < ?', $cutoff)
            ->having('last_login_at IS NULL OR last_login_at < ?', $cutoff)
            ->limit(self::BATCH_SIZE);

        foreach ($connection->fetchAll($select) as $row) {
            $customerId = (int) $row['entity_id'];

            try {
                if ($this->anonymizer->hasOpenOrders($customerId)) {
                    continue;
                }
                $this->anonymizer->anonymizeCustomer($customerId);
            } catch (\Throwable $e) {
                // One unsaveable customer must not abort the batch - otherwise
                // the same row fails again every night and nothing behind it is
                // ever reached.
                $this->logger->error(
                    sprintf(
                        'Magenx_Gdpr: failed to anonymize dormant customer %d: %s',
                        $customerId,
                        $e->getMessage()
                    ),
                    ['exception' => $e]
                );
            }
        }
    }
}
