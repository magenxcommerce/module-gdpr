<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Console\Command;

use Magenx\Gdpr\Model\CookieRegistrySeeder;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * (Re-)installs the default cookie groups and cookies by their natural key,
 * for a store that has since edited or deleted them and wants to start over.
 * Existing rows for other codes/names are left untouched.
 */
class SeedCookiesCommand extends Command
{
    public function __construct(
        private readonly CookieRegistrySeeder $seeder,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('magenx:gdpr:seed-cookies');
        $this->setDescription('Install or restore the default GDPR cookie groups and cookies.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // Area code already set (e.g. run from within a bootstrapped app).
        }

        $this->seeder->seed();
        $output->writeln('<info>Default GDPR cookie groups and cookies installed.</info>');

        return Command::SUCCESS;
    }
}
