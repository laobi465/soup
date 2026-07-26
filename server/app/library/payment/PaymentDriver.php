<?php
declare (strict_types = 1);

namespace app\library\payment;

abstract class PaymentDriver
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function createOrder(array $order): array;

    abstract public function queryOrder(string $orderNo): array;

    abstract public function refund(array $order): array;

    abstract public function verifyNotify(array $data): bool;

    abstract public function getNotifyData(): array;
}
