<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Console\Command;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableCommand extends Command
{
    public function __construct(
        private readonly WriterInterface $writer,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('merlin:checkoutprobe:enable')
            ->setDescription('Enable Merlin Checkout Probe (website scope)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try { $this->state->setAreaCode('adminhtml'); } catch (\Throwable) {}
        $this->writer->save('merlin_checkoutprobe/general/enabled', '1', ScopeInterface::SCOPE_DEFAULT, 0);
        $output->writeln('<info>Enabled.</info>');
        return Command::SUCCESS;
    }
}
