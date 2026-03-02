<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Plugin\App;

use Magento\Framework\App\FrontController;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\ContextCollector;
use Merlin\CheckoutProbe\Model\EventWriter;

class FrontControllerExceptionLogger
{
    public function __construct(
        private readonly Config $config,
        private readonly ContextCollector $collector,
        private readonly EventWriter $writer
    ) {}

    public function aroundDispatch(FrontController $subject, callable $proceed, RequestInterface $request): ResponseInterface
    {
        if (!$this->config->isEnabled()) {
            return $proceed($request);
        }

        try {
            return $proceed($request);
        } catch (\Throwable $e) {
            $ctx = $this->collector->collect($request);

            $this->writer->write([
                'event_type' => 'exception',
                'redirect_to' => null,
                'context_json' => [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => substr($e->getTraceAsString(), 0, 20000),
                ],
            ] + $ctx);

            throw $e;
        }
    }
}
