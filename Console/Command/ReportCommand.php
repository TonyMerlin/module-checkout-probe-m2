<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Merlin\CheckoutProbe\Model\KlarnaLogInspector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReportCommand extends Command
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly KlarnaLogInspector $klarnaInspector,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('merlin:checkoutprobe:report')
            ->setDescription('Generate a correlated debug report for a quote/session and recent probe events')
            ->addOption('quote-id', null, InputOption::VALUE_OPTIONAL, 'Magento quote_id')
            ->addOption('session-id', null, InputOption::VALUE_OPTIONAL, 'Klarna session_id (uuid)')
            ->addOption('minutes', null, InputOption::VALUE_OPTIONAL, 'Lookback window in minutes', '20')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max rows/lines per section', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try { $this->state->setAreaCode('adminhtml'); } catch (\Throwable) {}

        $quoteId   = $input->getOption('quote-id');
        $sessionId = (string)($input->getOption('session-id') ?? '');
        $minutes   = max(1, (int)$input->getOption('minutes'));
        $limit     = max(10, (int)$input->getOption('limit'));

        $quoteId = ($quoteId !== null && $quoteId !== '') ? (int)$quoteId : null;
        $sessionId = $sessionId !== '' ? $sessionId : null;

        $since = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-' . $minutes . ' minutes');

        $output->writeln('<info>Merlin CheckoutProbe report</info>');
        $output->writeln('Since (UTC): ' . $since->format('Y-m-d H:i:s'));
        $output->writeln('quote_id: ' . ($quoteId !== null ? (string)$quoteId : '(none)'));
        $output->writeln('session_id: ' . ($sessionId ?: '(none)'));
        $output->writeln('');

        $this->sectionProbeEvents($output, $since, $quoteId, $sessionId, $limit);
        $this->sectionKlarnaPaymentsQuote($output, $quoteId);
        $this->sectionKlarnaLogsTable($output, $quoteId, $sessionId, $limit);
        $this->sectionKlarnaLogFile($output, $quoteId, $sessionId);

        return 0;
    }

    private function sectionProbeEvents(OutputInterface $output, \DateTimeImmutable $since, ?int $quoteId, ?string $sessionId, int $limit): void
    {
        $conn = $this->resource->getConnection();
        $table = $conn->getTableName('merlin_checkoutprobe_event');

        $where = ['created_at >= ?'];
        $bind  = [$since->format('Y-m-d H:i:s')];

        // Filter by quote/session if possible (best-effort string search in context_json)
        if ($quoteId !== null) {
            $where[] = 'context_json LIKE ?';
            $bind[]  = '%"quote_id":' . $quoteId . '%';
        }
        if ($sessionId) {
            $where[] = 'context_json LIKE ?';
            $bind[]  = '%' . $sessionId . '%';
        }

        $sql = 'SELECT event_id, created_at, event_type, level, path, method, status_code
                FROM ' . $table . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY event_id DESC
                LIMIT ' . (int)$limit;

        $rows = $conn->fetchAll($sql, $bind);

        $output->writeln('<comment>== Probe events (merlin_checkoutprobe_event) ==</comment>');
        if (!$rows) {
            $output->writeln('(none)');
            $output->writeln('');
            return;
        }

        foreach ($rows as $r) {
            $output->writeln(sprintf(
                '#%s %s [%s] %s %s (%s)',
                $r['event_id'],
                $r['created_at'],
                $r['level'],
                $r['method'],
                $r['path'],
                $r['status_code'] ?? 'n/a'
            ));
        }
        $output->writeln('');
    }

    private function sectionKlarnaPaymentsQuote(OutputInterface $output, ?int $quoteId): void
    {
        $output->writeln('<comment>== klarna_payments_quote ==</comment>');
        if ($quoteId === null) {
            $output->writeln('(quote_id not provided)');
            $output->writeln('');
            return;
        }

        $conn = $this->resource->getConnection();
        $table = $conn->getTableName('klarna_payments_quote');

        try {
            $row = $conn->fetchRow('SELECT * FROM ' . $table . ' WHERE quote_id = ? ORDER BY updated_at DESC LIMIT 1', [$quoteId]);
        } catch (\Throwable $e) {
            $output->writeln('(unable to query table: ' . $e->getMessage() . ')');
            $output->writeln('');
            return;
        }

        if (!$row) {
            $output->writeln('(no row)');
            $output->writeln('');
            return;
        }

        foreach ($row as $k => $v) {
            $output->writeln($k . ': ' . (is_scalar($v) || $v === null ? (string)$v : '[complex]'));
        }
        $output->writeln('');
    }

    private function sectionKlarnaLogsTable(OutputInterface $output, ?int $quoteId, ?string $sessionId, int $limit): void
    {
        $output->writeln('<comment>== klarna_logs (table) ==</comment>');
        $res = $this->klarnaInspector->fetchDbLogs($quoteId, $sessionId, $limit);

        if (!$res['exists']) {
            $output->writeln('(table not found)');
            $output->writeln('');
            return;
        }

        if (!$res['rows']) {
            $output->writeln('(no matching rows)');
            $output->writeln('');
            return;
        }

        foreach (array_slice($res['rows'], 0, $limit) as $row) {
            // best-effort short line
            $parts = [];
            foreach (['created_at','updated_at','level','type','message','request','response','quote_id','session_id'] as $k) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    $parts[] = $k . '=' . (string)$row[$k];
                }
            }
            if (!$parts) {
                $parts[] = json_encode($row);
            }
            $output->writeln(implode(' | ', $parts));
        }

        $output->writeln('');
    }

    private function sectionKlarnaLogFile(OutputInterface $output, ?int $quoteId, ?string $sessionId): void
    {
        $output->writeln('<comment>== var/log/klarna.log (tail/filter) ==</comment>');
        $res = $this->klarnaInspector->tailFileLogs($quoteId, $sessionId);

        if (!$res['exists']) {
            $output->writeln('(file not found: ' . $res['path'] . ')');
            $output->writeln('');
            return;
        }

        if (!$res['lines']) {
            $output->writeln('(no matching lines in tail)');
            $output->writeln('');
            return;
        }

        foreach ($res['lines'] as $line) {
            $output->writeln($line);
        }
        $output->writeln('');
    }
}
