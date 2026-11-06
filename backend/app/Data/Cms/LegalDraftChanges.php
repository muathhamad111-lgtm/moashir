<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Support\ValueObjects\LocalizedText;

final readonly class LegalDraftChanges
{
    public function __construct(
        public ?string $version,
        public ?LocalizedText $title,
        public ?LocalizedText $body,
    ) {}

    /**
     * @return array<string, string|LocalizedText>
     */
    public function attributes(): array
    {
        return array_filter(
            ['version' => $this->version, 'title' => $this->title, 'body' => $this->body],
            static fn (string|LocalizedText|null $value): bool => $value !== null,
        );
    }
}
