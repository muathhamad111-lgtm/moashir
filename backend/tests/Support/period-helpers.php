<?php

declare(strict_types=1);

use App\Actions\Periods\CloseEndedPeriods;
use App\Actions\Periods\GeneratePeriods;
use App\Enums\Audit\AuditEvent;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Settings\SettingsStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

const PERIOD_ROLLOUT = '2027-01-01';

const RIYADH = 'Asia/Riyadh';

function atRiyadh(string $localDateTime): CarbonImmutable
{
    $moment = CarbonImmutable::parse($localDateTime, RIYADH);
    Date::setTestNow($moment);

    return $moment;
}

function setPeriodicSetting(SettingKey $key, mixed $value): void
{
    app(SettingsStore::class)->put($key, $value, AuditEvent::PeriodicSettingsUpdated, null);
}

/**
 * @return list<int>
 */
function livePeriods(string $rollout = PERIOD_ROLLOUT, PeriodCadence $cadence = PeriodCadence::Quarterly): array
{
    setPeriodicSetting(SettingKey::PeriodicRolloutFrom, $rollout);
    setPeriodicSetting(SettingKey::PeriodicCadence, $cadence->value);

    return generatePeriods();
}

/**
 * @return list<int>
 */
function generatePeriods(): array
{
    return app(GeneratePeriods::class)->handle();
}

function closePeriods(): int
{
    return app(CloseEndedPeriods::class)->handle();
}

function periodByKey(string $key): AssessmentPeriod
{
    return AssessmentPeriod::query()->live()->where('key', $key)->firstOrFail();
}

function submitDraft(Assessment $draft): Assessment
{
    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk();

    return $draft->refresh();
}

function completedInPeriod(CriteriaVersion $version, User $assessor, ?Entity $entity = null): Assessment
{
    $assessment = readyAccreditedDraft($version, $entity);
    submitDraft($assessment);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => accreditedFeedback($version)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();

    return $assessment->refresh();
}
