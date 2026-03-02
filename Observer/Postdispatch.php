<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\ContextCollector;
use Merlin\CheckoutProbe\Model\EventWriter;

class Postdispatch implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ContextCollector $collector,
        private readonly EventWriter $writer
    ) {}

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        /** @var RequestInterface $request */
        $request = $observer->getEvent()->getRequest();
        $ctx = $this->collector->collect($request);

        $this->writer->write([
            'event_type' => 'postdispatch',
            'redirect_to' => null,
            'context_json' => ['ctx' => $ctx],
        ] + $ctx);
    }
}
