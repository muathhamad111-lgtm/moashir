<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\PathSettingsUpdate;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\PathSetting;
use App\Exceptions\Settings\SettingsRejected;
use App\Jobs\Assessments\RecomputeAccreditedDueDatesJob;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Services\Settings\PathSettingsReader;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePathSettings
{
    private const string ASSESSMENT_SECTION = 'assessment';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private PathSettingsReader $reader,
        private RequestContext $context,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(User $actor, AssessmentPath $path, PathSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        if ($actor->isSuperAdmin() && ($update->reason === null || $update->reason === '')) {
            throw SettingsRejected::reasonRequired();
        }

        $this->context->recordReason($update->reason);
        $changed = DB::transaction(fn (): array => $this->write($actor, $path, $update));

        if ($path === AssessmentPath::Accredited && in_array(PathSetting::ReviewSlaDays->value, $changed, true)) {
            RecomputeAccreditedDueDatesJob::dispatch();
        }

        if ($changed !== [] && $actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }

    /**
     * @return list<string>
     */
    private function write(User $actor, AssessmentPath $path, PathSettingsUpdate $update): array
    {
        $this->lock->lock(self::ASSESSMENT_SECTION);
        $changed = [];

        foreach ($update->scalarValues() as $field => $value) {
            if ($this->store->put(PathSetting::from($field)->keyFor($path), $value, AuditEvent::AssessmentSettingsUpdated, $actor)) {
                $changed[] = $field;
            }
        }

        if ($update->textChanges !== [] && $this->writeTexts($actor, $path, $update)) {
            $changed[] = PathSetting::EntityTexts->value;
        }

        return $changed;
    }

    private function writeTexts(User $actor, AssessmentPath $path, PathSettingsUpdate $update): bool
    {
        $texts = $this->reader->textsOf($path)->replacing($update->textChanges);

        return $this->store->put(PathSetting::EntityTexts->keyFor($path), $texts->toArray(), AuditEvent::AssessmentSettingsUpdated, $actor);
    }
}
