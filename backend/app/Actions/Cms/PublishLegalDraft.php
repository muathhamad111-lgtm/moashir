<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Identity\TermsVersionPublisher;
use App\Services\Settings\SettingsChangeReason;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;

final readonly class PublishLegalDraft
{
    public function __construct(
        private SettingsChangeReason $reason,
        private TermsVersionPublisher $publisher,
        private Repository $config,
    ) {}

    public function handle(User $actor, TermsVersion $draft, ?string $reason): TermsVersion
    {
        $this->reason->record($actor, $reason);
        $today = CarbonImmutable::now($this->config->string('app.display_timezone'))->startOfDay();
        $published = $this->publisher->publishDraft($actor, $draft, $today);
        $this->reason->alertIfSuperAdmin($actor);

        return $published;
    }
}
