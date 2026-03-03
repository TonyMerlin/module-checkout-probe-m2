<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Plugin\App;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\ContextCollector;
use Merlin\CheckoutProbe\Model\EventWriter;
use Psr\Log\LoggerInterface;

class FrontControllerExceptionLogger
{
    public function __construct(
        private readonly Config $config,
        private readonly ContextCollector $contextCollector,
        private readonly EventWriter $eventWriter,
        private readonly LoggerInterface $logger,
        private readonly ResponseInterface $response
    ) {}

    public function aroundDispatch(
        FrontControllerInterface $subject,
        \Closure $proceed,
        RequestInterface $request
    ): ResponseInterface {
        $result = null;

        try {
            $result = $proceed($request);

            if ($this->config->isEnabled()) {
                // Optional: record a lightweight “dispatch ok” event
                // $this->eventWriter->write('dispatch_ok', $this->contextCollector->collect($request));
            }

            return $this->normalizeToResponse($result);
        } catch (\Throwable $e) {
            if ($this->config->isEnabled()) {
                $ctx = $this->contextCollector->collect($request);

                // Persist structured event for correlation (quote_id/masked_id/etc)
                $this->eventWriter->write('dispatch_exception', $ctx + [
                    'exception_class' => get_class($e),
                    'message'         => $e->getMessage(),
                ]);

                // Also log full exception
                $this->logger->critical('[CheckoutProbe] Unhandled exception during dispatch', [
                    'exception' => $e,
                    'path_info' => $request->getPathInfo(),
                    'method'    => $request->getMethod(),
                    'probe'     => $ctx,
                ]);
            }

            throw $e;
        }
    }

    /**
     * @param mixed $result
     */
    private function normalizeToResponse($result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($result instanceof ResultInterface) {
            $result->renderResult($this->response);
            return $this->response;
        }

        return $this->response;
    }
}
