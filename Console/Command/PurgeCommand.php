<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Merlin\CheckoutProbe\Model\Config as ProbeConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeCommand extends Command
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProbeConfig $config,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('merlin:checkoutprobe:purge')
            ->setDescription('Purge old Merlin Checkout Probe DB rows based on retention setting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try { $this->state->setAreaCode('adminhtml'); } catch (\Throwable) {}

        $days = $this->config->retainDays();
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName('merlin_checkoutprobe_event');

        $sql = sprintf("DELETE FROM %s WHERE created_at < (NOW() - INTERVAL %d DAY)", $table, (int)$days);
        $affected = $conn->query($sql)->rowCount();

        $output->writeln("<info>Purged {$affected} rows older than {$days} days.</info>");
        return Command::SUCCESS;
    }
}
