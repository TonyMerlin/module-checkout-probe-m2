<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Quote\Model\QuoteIdMaskFactory;

class ContextCollector
{
    public function __construct(
        private readonly State $state,
        private readonly RemoteAddress $remoteAddress,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function collect(RequestInterface $request): array
    {
        $area = null;
        try { $area = $this->state->getAreaCode(); } catch (\Throwable) {}

        $phpSid = $request->getCookie('PHPSESSID') ?: null;

        $quoteId = null;
        $reservedOrderId = null;
        $paymentMethod = null;
        $masked = null;

        try {
            $q = $this->checkoutSession->getQuote();
            if ($q && $q->getId()) {
                $quoteId = (int)$q->getId();
                $reservedOrderId = (string)$q->getReservedOrderId();
                $paymentMethod = $q->getPayment() ? (string)$q->getPayment()->getMethod() : null;

                // try resolve mask (best-effort)
                $mask = $this->quoteIdMaskFactory->create();
                $mask->load((int)$q->getId(), 'quote_id');
                $masked = $mask->getMaskedId() ?: null;
            }
        } catch (\Throwable) {
            // ignore
        }

        $customerId = null;
        try { $customerId = $this->customerSession->getCustomerId() ? (int)$this->customerSession->getCustomerId() : null; } catch (\Throwable) {}

        $lastRealOrderId = null;
        try { $lastRealOrderId = (string)$this->checkoutSession->getLastRealOrderId(); } catch (\Throwable) {}

        return [
            'area' => $area,
            'full_action_name' => $request->getFullActionName() ?: null,
            'request_uri' => $request->getRequestUri() ?: null,
            'http_method' => $request->getMethod() ?: null,
            'remote_ip' => $this->remoteAddress->getRemoteAddress() ?: null,
            'php_session_id' => $phpSid,
            'mage_cache_sid' => $request->getCookie('MAGE_CACHE_SID') ?: null,
            'private_content_version' => $request->getCookie('private_content_version') ?: null,
            'quote_id' => $quoteId,
            'masked_quote_id' => $masked,
            'reserved_order_id' => $reservedOrderId ?: null,
            'customer_id' => $customerId,
            'last_real_order_id' => $lastRealOrderId ?: null,
            'payment_method' => $paymentMethod,
        ];
    }
}
