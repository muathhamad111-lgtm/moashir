<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Data\Nda\NdaPublication;
use App\Enums\Notifications\NotificationCode;
use App\Jobs\Nda\SendNdaNotices;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Cms\LegalDraftScope;
use App\Services\Identity\TermsVersionPublisher;
use App\Services\Nda\NdaDraftFinder;
use App\Services\Settings\SettingsChangeReason;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the agreement draft with its change summary. Every in-scope user must accept it before their next
 * request; NDA-01 tells those who have not, once the transaction has committed.
 */
final readonly class PublishNdaDraft
{
    public function __construct(
        private NdaDraftFinder $drafts,
        private LegalDraftScope $scope,
        private TermsVersionPublisher $publisher,
        private SettingsChangeReason $reason,
        private Repository $config,
    ) {}

    public function handle(User $actor, NdaPublication $publication): TermsVersion
    {
        $this->reason->record($actor, $publication->reason);
        $draft = $this->drafts->findOrFail();

        $published = DB::transaction(function () use ($actor, $draft, $publication): TermsVersion {
            $this->scope->onDraft($draft, static fn (TermsVersion $locked): bool => $locked->forceFill(['change_summary' => $publication->changeSummary])->save());

            return $this->publisher->publishDraft($actor, $draft, $this->today());
        });

        SendNdaNotices::dispatch($published->id, NotificationCode::NdaVersionPublished)->afterCommit();
        $this->reason->alertIfSuperAdmin($actor);

        return $published;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->config->string('app.display_timezone'))->startOfDay();
    }
}
