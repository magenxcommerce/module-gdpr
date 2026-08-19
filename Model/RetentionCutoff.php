<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

/**
 * Turns a retention window in days into the UTC datetime string the retention
 * crons compare stored timestamps against.
 *
 * Explicitly UTC rather than PHP's ambient timezone: Magento stores every
 * created_at / last_login_at in UTC, and while Magento's own bootstrap happens
 * to set the default timezone to UTC, a cutoff that silently depends on that
 * would shift by hours the moment anything changed it - and every row inside
 * the shift is a customer record irreversibly anonymized early, or one kept
 * past its retention window.
 *
 * Deliberately not Magento\Framework\Stdlib\DateTime\DateTime::gmtDate(), whose
 * second argument converts a *store-local* time to GMT. Feeding it a relative
 * expression like "-365 days" applies the store's UTC offset a second time.
 */
class RetentionCutoff
{
    /** The UTC datetime, `$days` ago, as a 'Y-m-d H:i:s' string for SQL comparison. */
    public function since(int $days): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d days', $days))
            ->format('Y-m-d H:i:s');
    }
}
