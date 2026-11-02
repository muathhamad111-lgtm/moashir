<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class SmsSender
{
    public function __construct(public string $name, public string $status, public ?string $destination) {}

    public function isActive(): bool
    {
        return mb_strtolower($this->status) === 'active';
    }

    /**
     * @return array{sender_name: string, status: string, destination: string|null}
     */
    public function toArray(): array
    {
        return ['sender_name' => $this->name, 'status' => $this->status, 'destination' => $this->destination];
    }
}
