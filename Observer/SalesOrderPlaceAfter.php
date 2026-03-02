<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\EventWriter;

class SalesOrderPlaceAfter implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly EventWriter $writer
    ) {}

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        $this->writer->write([
            'event_type' => 'sales_order_place_after',
            'order_id' => (int)$order->getId(),
            'order_increment_id' => (string)$order->getIncrementId(),
            'payment_method' => $order->getPayment()?->getMethod(),
            'context_json' => [
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'grand_total' => $order->getGrandTotal(),
            ],
        ]);
    }
}
