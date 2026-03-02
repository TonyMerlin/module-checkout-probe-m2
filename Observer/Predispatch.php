<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\ContextCollector;
use Merlin\CheckoutProbe\Model\EventWriter;
use Psr\Log\LoggerInterface;

class Predispatch implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ContextCollector $collector,
        private readonly EventWriter $writer,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var RequestInterface $request */
        $request = $observer->getEvent()->getRequest();
        $ctx = $this->collector->collect($request);

        // Capture token/session_id parameters if present (important for Klarna)
        $params = $request->getParams();
        $interesting = [];
        foreach (['token','session_id','maskedQuoteId','quoteId','cartId'] as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $interesting[$k] = (string)$params[$k];
            }
        }

        $this->writer->write([
            'event_type' => 'predispatch',
            'redirect_to' => null,
            'context_json' => [
                'ctx' => $ctx,
                'params' => $interesting,
            ],
        ] + $ctx);

        $this->logger->info('[CheckoutProbe] predispatch', ['full_action_name' => $ctx['full_action_name'], 'params' => $interesting]);
    }
}
