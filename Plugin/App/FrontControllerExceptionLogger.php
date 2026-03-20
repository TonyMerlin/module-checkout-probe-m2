<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Plugin\App;

use Magento\Framework\App\FrontController;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\ContextCollector;
use Merlin\CheckoutProbe\Model\EventWriter;
use Psr\Log\LoggerInterface;

class FrontControllerExceptionLogger
{
    private ResponseInterface $response;

    public function __construct(
        private readonly Config $config,
        private readonly ContextCollector $contextCollector,
        private readonly EventWriter $eventWriter,
        private readonly LoggerInterface $logger,
        ?ResponseInterface $response = null
    ) {
        $this->response = $response
            ?? \Magento\Framework\App\ObjectManager::getInstance()->get(ResponseInterface::class);
    }

    public function aroundDispatch(
        FrontController $subject,
        \Closure $proceed,
        RequestInterface $request
    ): ResponseInterface {
        try {
            $result = $proceed($request);

            if ($this->config->isEnabled() && $this->shouldProbePath((string)$request->getPathInfo())) {
                try {
                    $this->eventWriter->write('dispatch_ok', $this->contextCollector->collect($request));
                } catch (\Throwable $ignored) {
                }
            }

            return $this->normalizeToResponse($result);
        } catch (\Throwable $e) {
            if ($this->config->isEnabled()) {
                try {
                    $ctx = $this->contextCollector->collect($request);
                    $ctx['exception_class'] = get_class($e);
                    $ctx['message'] = $e->getMessage();
                    $ctx['path'] = (string)$request->getPathInfo();
                    $ctx['method'] = (string)$request->getMethod();

                    $this->eventWriter->write('dispatch_exception', $ctx);

                    $this->logger->critical('[CheckoutProbe] Unhandled exception during dispatch', [
                        'exception' => $e,
                        'probe' => $ctx,
                    ]);
                } catch (\Throwable $ignored) {
                }
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

    private function shouldProbePath(string $path): bool
    {
        return str_contains($path, '/checkout')
            || str_contains($path, '/klarna')
            || str_contains($path, '/amcookie');
    }
}
