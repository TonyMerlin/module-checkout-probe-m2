<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED     = 'merlin_checkoutprobe/general/enabled';
    private const XML_PATH_RETAIN_DAYS = 'merlin_checkoutprobe/general/retain_days';
    private const XML_PATH_LOG_BODY_MAX = 'merlin_checkoutprobe/general/log_body_max';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig) {}

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_WEBSITE, $storeId);
    }

    public function retainDays(?int $storeId = null): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XML_PATH_RETAIN_DAYS, ScopeInterface::SCOPE_WEBSITE, $storeId);
        return $v > 0 ? $v : 7;
    }

    public function logBodyMax(?int $storeId = null): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XML_PATH_LOG_BODY_MAX, ScopeInterface::SCOPE_WEBSITE, $storeId);
        return $v >= 0 ? $v : 4096;
    }
}
