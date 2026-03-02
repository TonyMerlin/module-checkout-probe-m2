<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Model;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;

class Logger extends MonoLogger
{
    public function __construct()
    {
        parent::__construct('merlin_checkout_probe');
        $this->pushHandler(new StreamHandler(BP . '/var/log/merlin_checkout_probe.log'));
    }
}
