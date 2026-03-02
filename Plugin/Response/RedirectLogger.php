<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Plugin\Response;

use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\RequestInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\ContextCollector;
use Merlin\CheckoutProbe\Model\EventWriter;

class RedirectLogger
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly ContextCollector $collector,
        private readonly EventWriter $writer
    ) {}

    public function beforeSetRedirect(ResponseHttp $subject, $url, $code = 302): array
    {
        if (!$this->config->isEnabled()) {
            return [$url, $code];
        }

        $ctx = $this->collector->collect($this->request);

        // Only log interesting redirects (cart, checkout, success) to reduce noise
        $u = (string)$url;
        $interesting = (stripos($u, '/checkout/cart') !== false)
            || (stripos($u, '/checkout') !== false)
            || (stripos($u, '/onepage/success') !== false);

        if ($interesting) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
            $stack = [];
            foreach ($bt as $frame) {
                if (!isset($frame['file'])) continue;
                $stack[] = ($frame['file'] ?? '') . ':' . ($frame['line'] ?? '');
            }

            $this->writer->write([
                'event_type' => 'redirect',
                'redirect_to' => $u,
                'context_json' => [
                    'http_code' => $code,
                    'stack' => $stack,
                ],
            ] + $ctx);
        }

        return [$url, $code];
    }
}
