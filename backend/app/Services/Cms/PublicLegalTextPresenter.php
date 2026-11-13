<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Identity\TermsVersion;
use App\Support\RichText\RichTextParser;
use App\Support\ValueObjects\LocalizedText;

final readonly class PublicLegalTextPresenter
{
    public function __construct(private RichTextParser $parser) {}

    /**
     * @return array<string, mixed>
     */
    public function present(TermsVersion $version): array
    {
        $title = $version->title ?? LocalizedText::fromTranslation('legal.titles.'.$version->kind);

        return [
            'id' => $version->id,
            'kind' => $version->kind,
            'version' => $version->version,
            'effective_from' => $version->effective_from?->toDateString(),
            'published_at' => $version->published_at?->toIso8601String(),
            'title' => $title->toArray(),
            'body' => $this->parser->localized($version->body->toArray()),
        ];
    }
}
