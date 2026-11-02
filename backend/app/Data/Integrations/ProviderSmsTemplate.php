<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class ProviderSmsTemplate
{
    public function __construct(public string $id, public string $title, public string $body) {}

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            is_scalar($values['id'] ?? null) ? (string) $values['id'] : '',
            is_string($values['title'] ?? null) ? $values['title'] : '',
            is_string($values['body'] ?? null) ? $values['body'] : '',
        );
    }

    /**
     * @return array{id: string, title: string, body: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'body' => $this->body];
    }
}
