<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class ConnectionCheck
{
    public function __construct(public string $key, public bool $ok, public ?string $detailCode = null) {}

    public static function passed(string $key): self
    {
        return new self($key, true);
    }

    public static function failed(string $key, string $detailCode): self
    {
        return new self($key, false, $detailCode);
    }

    /**
     * @return array{key: string, ok: bool, detail_code: string|null}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'ok' => $this->ok, 'detail_code' => $this->detailCode];
    }
}
