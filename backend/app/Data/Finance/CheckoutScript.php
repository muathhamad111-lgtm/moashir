<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class CheckoutScript
{
    private const string INTEGRITY_PATTERN = '/^sha(256|384|512)-[A-Za-z0-9+\/]+={0,2}$/';

    public ?string $integrity;

    public function __construct(public string $url, ?string $integrity)
    {
        $this->integrity = $integrity !== null && preg_match(self::INTEGRITY_PATTERN, $integrity) === 1 ? $integrity : null;
    }

    /**
     * @return array{url: string, integrity: string|null}
     */
    public function toArray(): array
    {
        return ['url' => $this->url, 'integrity' => $this->integrity];
    }
}
