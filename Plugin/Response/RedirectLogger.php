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
        private readonly ContextCollector $contextCollector,
        private readonly EventWriter $eventWriter
    ) {}

    public function afterSetRedirect(ResponseHttp $subject, ResponseHttp $result, $url, $code = 302): ResponseHttp
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        $ctx = $this->contextCollector->collect($this->request);

        $this->eventWriter->write('redirect', $ctx + [
            'to'   => (string) $url,
            'code' => (int) $code,
        ], 'info');

        return $result;
    }
}
