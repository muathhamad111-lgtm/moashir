<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Models\Identity\TermsVersion;
use App\Services\Cms\PublicLegalTextPresenter;
use App\Support\RichText\RichTextParser;

/**
 * The agreement as the acceptance modal and the invitation step show it: the parsed text blocks (rendered
 * without HTML by the SPA) and the change summary of the version.
 */
final readonly class NdaTextPresenter
{
    public function __construct(private PublicLegalTextPresenter $legal, private RichTextParser $parser) {}

    /**
     * @return array<string, mixed>
     */
    public function present(TermsVersion $version): array
    {
        return [
            ...$this->legal->present($version),
            'change_summary' => $version->change_summary === null ? null : $this->parser->localized($version->change_summary->toArray()),
        ];
    }
}
