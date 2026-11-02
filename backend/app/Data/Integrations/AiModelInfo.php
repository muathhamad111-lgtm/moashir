<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class AiModelInfo
{
    public function __construct(public string $id, public string $displayName, public ?int $maxOutputTokens = null) {}

    /**
     * @return array{id: string, display_name: string, max_output_tokens: int|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'display_name' => $this->displayName, 'max_output_tokens' => $this->maxOutputTokens];
    }
}
