<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class SmsBalance
{
    public function __construct(
        public string $amount,
        public ?string $currency = null,
        public ?string $accountStatus = null,
        public ?string $expiresOn = null,
    ) {}

    public function isEmpty(): bool
    {
        return ! is_numeric($this->amount) || (float) $this->amount <= 0.0;
    }

    public function isAccountInactive(): bool
    {
        return $this->accountStatus !== null && mb_strtolower($this->accountStatus) !== 'active';
    }

    /**
     * @return array<string, string|null>
     */
    public function toInfo(): array
    {
        return [
            'balance' => $this->amount,
            'currency' => $this->currency,
            'account_status' => $this->accountStatus,
            'account_expiry_date' => $this->expiresOn,
        ];
    }
}
