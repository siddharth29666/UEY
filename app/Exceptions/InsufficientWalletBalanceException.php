<?php

namespace App\Exceptions;

class InsufficientWalletBalanceException extends \Exception
{
    protected float $balance;

    protected float $required;

    protected float $shortfall;

    public function __construct(
        float $balance,
        float $required,
        float $shortfall,
        string $message = 'Insufficient wallet balance.'
    ) {
        parent::__construct($message);
        $this->balance = $balance;
        $this->required = $required;
        $this->shortfall = $shortfall;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getRequired(): float
    {
        return $this->required;
    }

    public function getShortfall(): float
    {
        return $this->shortfall;
    }
}
