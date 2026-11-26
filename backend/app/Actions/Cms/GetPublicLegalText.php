<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Identity\TermsKind;
use App\Exceptions\Cms\LegalTextRejected;
use App\Models\Identity\TermsVersion;
use App\Services\Cms\PublicContentCache;
use App\Services\Cms\PublicLegalTextPresenter;
use App\Services\Identity\CurrentTermsVersions;

final readonly class GetPublicLegalText
{
    public function __construct(
        private PublicContentCache $cache,
        private CurrentTermsVersions $terms,
        private PublicLegalTextPresenter $presenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(TermsKind $kind): array
    {
        $text = $this->cache->legalText($kind, function () use ($kind): ?array {
            $current = $this->terms->of($kind);

            return $current instanceof TermsVersion ? $this->presenter->present($current) : null;
        });

        return $text ?? throw LegalTextRejected::notPublished();
    }
}
