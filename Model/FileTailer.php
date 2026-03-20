<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Model;

class FileTailer
{
    /**
     * Return last N bytes of a file (best-effort).
     */
    public function tail(string $path, int $bytes = 262144): string
    {
        if ($bytes <= 0 || !is_file($path) || !is_readable($path)) {
            return '';
        }

        $size = @filesize($path);
        if ($size === false) {
            return '';
        }

        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return '';
        }

        try {
            $start = max(0, $size - $bytes);
            @fseek($fh, $start);

            $data = '';
            while (!feof($fh)) {
                $chunk = fread($fh, 8192);
                if ($chunk === false) {
                    break;
                }
                $data .= $chunk;
                if (strlen($data) > $bytes * 2) {
                    // safety
                    $data = substr($data, -$bytes);
                }
            }

            return $data;
        } finally {
            fclose($fh);
        }
    }
}
