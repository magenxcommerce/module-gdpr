<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Setup\Patch\Data;

use Magenx\Gdpr\Model\CookieRegistrySeeder;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Re-applies the seed on stores that installed before the registry gained the
 * NEXT_LOCALE row, the Google Ads rows, and the active Google Analytics rows.
 * InstallCookieRegistry has already run there, so it never fires again.
 *
 * The seeder upserts by natural key, so this adds the missing rows and restores
 * the shipped defaults on the rows that already exist.
 */
class AddLocaleAndMarketingCookies implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CookieRegistrySeeder $seeder
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->seeder->seed();
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [InstallCookieRegistry::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
