<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Typed reader over the magenx_gdpr/* config paths. Never read
 * ScopeConfigInterface directly from resolvers/cron - go through this.
 */
class Config
{
    private const XML_PATH_ENABLED = 'magenx_gdpr/general/enabled';
    private const XML_PATH_CONSENT_LOG_RETENTION_DAYS = 'magenx_gdpr/consent_log/retention_days';
    private const XML_PATH_DORMANT_ENABLED = 'magenx_gdpr/dormant_accounts/enabled';
    private const XML_PATH_DORMANT_DAYS = 'magenx_gdpr/dormant_accounts/days';
    private const XML_PATH_OLD_ORDERS_ENABLED = 'magenx_gdpr/old_orders/enabled';
    private const XML_PATH_OLD_ORDERS_DAYS = 'magenx_gdpr/old_orders/days';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getConsentLogRetentionDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CONSENT_LOG_RETENTION_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDormantAccountAnonymizationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DORMANT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getDormantAccountDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_DORMANT_DAYS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isOldOrderAnonymizationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_OLD_ORDERS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getOldOrderDays(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_OLD_ORDERS_DAYS, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
