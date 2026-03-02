<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Merlin\CheckoutProbe\Model\Config;
use Merlin\CheckoutProbe\Model\EventWriter;

class CheckoutSubmitAllAfter implements ObserverInterface
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

        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        $row = [
            'event_type' => 'checkout_submit_all_after',
            'context_json' => [
                'quote_id' => $quote?->getId(),
                'reserved_order_id' => $quote?->getReservedOrderId(),
                'payment_method' => $quote?->getPayment()?->getMethod(),
                'order_id' => $order?->getId(),
                'order_increment_id' => $order?->getIncrementId(),
                'order_state' => $order?->getState(),
                'order_status' => $order?->getStatus(),
            ]
        ];

        $row['quote_id'] = $quote?->getId() ? (int)$quote->getId() : null;
        $row['reserved_order_id'] = $quote?->getReservedOrderId() ?: null;
        $row['payment_method'] = $quote?->getPayment()?->getMethod() ?: null;
        $row['order_id'] = $order?->getId() ? (int)$order->getId() : null;
        $row['order_increment_id'] = $order?->getIncrementId() ?: null;

        $this->writer->write($row);
    }
}
