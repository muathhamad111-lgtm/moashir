<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoLegalTexts;
use App\Data\Identity\TermsPublication;
use App\Enums\Cms\LegalDocument;
use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Cms\LegalDocumentSource;
use App\Services\Identity\CurrentTermsVersions;
use App\Services\Identity\TermsVersionPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use LogicException;

final readonly class DemoTermsInstaller
{
    public function __construct(
        private LegalDocumentSource $source,
        private CurrentTermsVersions $terms,
        private TermsVersionPublisher $publisher,
        private Repository $config,
    ) {}

    public function install(User $publisher): DemoLegalTexts
    {
        $currentIds = [];
        $published = 0;

        foreach (TermsKind::cases() as $kind) {
            $seed = $this->publishSeed($kind, $publisher);
            $published += $seed instanceof TermsVersion ? 1 : 0;
            $current = $seed ?? $this->terms->of($kind) ?? throw new LogicException('Every legal text kind has a current version after the demo installation.');
            $currentIds[] = $current->id;
        }

        return new DemoLegalTexts($currentIds, $published);
    }

    private function publishSeed(TermsKind $kind, User $publisher): ?TermsVersion
    {
        $seed = TermsVersion::query()->ofKind($kind)->where('version', LegalDocumentSource::SEED_VERSION)->first();

        if ($seed instanceof TermsVersion) {
            return $seed->isDraft() ? $this->publisher->publishDraft($publisher, $seed, $this->today()) : null;
        }

        $text = $this->source->read(LegalDocument::of($kind));

        return $this->publisher->publishNew($publisher, new TermsPublication($kind, LegalDocumentSource::SEED_VERSION, $text->body, $this->today(), $text->title));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->config->string('app.display_timezone'))->startOfDay();
    }
}
