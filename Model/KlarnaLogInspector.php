<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Model;

use Magento\Framework\App\ResourceConnection;

class KlarnaLogInspector
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly FileTailer $tailer
    ) {}

    /**
     * Best-effort fetch from `klarna_logs` if the table exists.
     *
     * @return array{exists:bool, columns:string[], rows:array<int, array<string,mixed>>}
     */
    public function fetchDbLogs(?int $quoteId, ?string $sessionId, int $limit = 200): array
    {
        $conn = $this->resource->getConnection();

        $table = $conn->getTableName('klarna_logs');
        $exists = (bool) $conn->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [basename((string)$table)]
        );

        if (!$exists) {
            return ['exists' => false, 'columns' => [], 'rows' => []];
        }

        $cols = $conn->fetchCol(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position',
            [basename((string)$table)]
        );

        $where = [];
        $bind  = [];

        if ($quoteId !== null && in_array('quote_id', $cols, true)) {
            $where[] = 'quote_id = ?';
            $bind[]  = $quoteId;
        }

        if ($sessionId && in_array('session_id', $cols, true)) {
            $where[] = 'session_id = ?';
            $bind[]  = $sessionId;
        }

        $sql = 'SELECT * FROM ' . $table;
        if ($where) {
            $sql .= ' WHERE ' . implode(' OR ', $where);
        }

        // Prefer a time column if present
        $orderCol = null;
        foreach (['created_at','updated_at','timestamp','time','logged_at'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                $orderCol = $candidate;
                break;
            }
        }
        $sql .= $orderCol ? (' ORDER BY ' . $orderCol . ' DESC') : ' ORDER BY 1 DESC';
        $sql .= ' LIMIT ' . (int) $limit;

        $rows = $conn->fetchAll($sql, $bind);

        return ['exists' => true, 'columns' => $cols, 'rows' => $rows];
    }

    /**
     * Tail var/log/klarna.log and filter by quote/session markers.
     */
    public function tailFileLogs(?int $quoteId, ?string $sessionId, int $bytes = 524288): array
    {
        $path = BP . '/var/log/klarna.log';
        $raw  = $this->tailer->tail($path, $bytes);

        if ($raw === '') {
            return ['path' => $path, 'exists' => is_file($path), 'lines' => []];
        }

        $lines = preg_split("/\r?\n/", $raw) ?: [];
        $out = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $hit = false;
            if ($quoteId !== null && strpos($line, (string)$quoteId) !== false) {
                $hit = true;
            }
            if ($sessionId && strpos($line, $sessionId) !== false) {
                $hit = true;
            }

            // Also include generic Klarna errors if we didn't have identifiers
            if (!$hit && ($quoteId === null && !$sessionId)) {
                if (stripos($line, 'Klarna.') !== false || stripos($line, 'klarna') !== false) {
                    $hit = true;
                }
            }

            if ($hit) {
                $out[] = $line;
            }
        }

        return ['path' => $path, 'exists' => true, 'lines' => array_slice($out, -200)];
    }
}
