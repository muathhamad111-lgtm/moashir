<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Award\CommitteeAverage;
use App\Models\Award\CommitteeScore;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<string>
 */
function noticeCodesFor(User $user): array
{
    return array_values(array_map(
        static fn (EntityNotification $notification): string => $notification->notice->code->value,
        Notification::sent($user, EntityNotification::class)->all(),
    ));
}

it('distributes every qualified request to every active committee assessor when the committee starts', function (): void {
    $scenario = committeeScenario(2, 2);
    [$first, $second] = $scenario['requests'];

    expect($first->status->value)->toBe('under_committee_assessment')
        ->and($second->status->value)->toBe('under_committee_assessment')
        ->and($scenario['cycle']->status)->toBe(AwardCycleStatus::CommitteeAssessment);

    foreach ($scenario['assessors'] as $assessor) {
        expect(committeeRowOf($assessor, $first)->status->value)->toBe('not_started')
            ->and(noticeCodesFor($assessor))->toContain(NotificationCode::CommitteeAssigned->value);
        switchTo($assessor)->getJson('/api/v1/committee/me/assignments')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    }

    expect(Audit::query()->where('event', AuditEvent::CycleCommitteeStarted->value)->count())->toBe(1);
});

it('shows the committee assessor a blind evaluation with the entity data allowed by the visibility setting', function (): void {
    $scenario = committeeScenario(1, 2);
    $assessment = $scenario['requests'][0];
    [$assessor, $other] = $scenario['assessors'];
    submitCommitteeEvaluation($other, $assessment, $scenario['version'])->assertOk();
    $row = committeeRowOf($assessor, $assessment);

    $view = switchTo($assessor)->getJson(myAssignmentPath($row))->assertOk()
        ->assertJsonPath('data.mode', 'edit')
        ->assertJsonPath('data.assignment.entity.visibility', 'name_only')
        ->assertJsonPath('data.assignment.entity.name.ar', $assessment->entity->name->toArray()['ar'])
        ->assertJsonPath('data.assignment.status', 'not_started')
        ->assertJsonPath('data.progress.scored', 0)
        ->assertJsonPath('data.prior', null)
        ->assertJsonStructure(['data' => ['criteria', 'cv', 'evidence', 'notes' => ['subs', 'general'], 'scale']]);

    $json = json_encode($view->json(), JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('initial_result')
        ->and($json)->not->toContain('average')
        ->and($json)->not->toContain($other->name)
        ->and($view->json('data.assignment.entity'))->not->toHaveKey('reference');

    app(SettingsStore::class)->put(SettingKey::CommitteeEntityDataVisibility, 'code_only', AuditEvent::AssessmentSettingsUpdated, systemAdmin());
    $coded = test()->getJson(myAssignmentPath($row))->assertOk()->assertJsonPath('data.assignment.entity.visibility', 'code_only');
    expect($coded->json('data.assignment.entity'))->not->toHaveKey('name')
        ->and($coded->json('data.assignment.entity.code'))->toStartWith('C-');

    app(SettingsStore::class)->put(SettingKey::CommitteeEntityDataVisibility, 'full', AuditEvent::AssessmentSettingsUpdated, systemAdmin());
    test()->getJson(myAssignmentPath($row))->assertOk()->assertJsonPath('data.assignment.entity.reference', $assessment->entity->reference());
});

it('records versioned scores and notes and starts the evaluation on the first save', function (): void {
    $scenario = committeeScenario(1, 1);
    $assessment = $scenario['requests'][0];
    $assessor = $scenario['assessors'][0];
    $row = committeeRowOf($assessor, $assessment);
    $sub = subOf($scenario['version'], 'M1.1');

    switchTo($assessor)->putJson(myAssignmentPath($row, 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '7'])])->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.progress.scored', 1);
    test()->putJson(myAssignmentPath($row, 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '8'])])->assertOk();
    test()->putJson(myAssignmentPath($row, 'notes'), ['notes' => [(string) $sub->id => 'ملاحظة على السياسة المعتمدة.'], 'general_note' => 'ملاحظة عامة على التقييم.'])->assertOk()
        ->assertJsonPath("data.subs.{$sub->id}", 'ملاحظة على السياسة المعتمدة.')
        ->assertJsonPath('data.general', 'ملاحظة عامة على التقييم.');
    test()->putJson(myAssignmentPath($row, 'notes'), ['notes' => [(string) $sub->id => null]])->assertOk()->assertJsonPath('data.subs', []);
    test()->putJson(myAssignmentPath($row, 'notes'), ['notes' => ['999999' => 'نص']])->assertUnprocessable()->assertJsonPath('code', 'assessment.sub_not_in_version');
    test()->putJson(myAssignmentPath($row, 'scores'), ['scores' => ['subs' => [(string) $sub->id => '11']]])->assertUnprocessable();

    expect(CommitteeScore::query()->where('committee_assignment_id', $row->id)->count())->toBe(2)
        ->and(CommitteeScore::query()->where('committee_assignment_id', $row->id)->current()->value('raw_value'))->toBe('8.00')
        ->and(noticeCodesFor($scenario['secretary']))->toContain(NotificationCode::CommitteeEvaluationStarted->value);
});

it('locks a submitted evaluation and computes the average automatically when the last assessor submits', function (): void {
    $scenario = committeeScenario(1, 2);
    $assessment = $scenario['requests'][0];
    [$first, $second] = $scenario['assessors'];
    $row = committeeRowOf($first, $assessment);

    switchTo($first)->postJson(myAssignmentPath($row, 'submit'))->assertUnprocessable()->assertJsonPath('code', 'scores.incomplete');
    submitCommitteeEvaluation($first, $assessment, $scenario['version'])->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.percent', '77.00');
    test()->putJson(myAssignmentPath($row, 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '1'])])->assertConflict()->assertJsonPath('code', 'committee.evaluation_locked');
    test()->getJson(myAssignmentPath($row))->assertOk()->assertJsonPath('data.mode', 'read_only');
    expect($assessment->refresh()->status->value)->toBe('under_committee_assessment');

    submitCommitteeEvaluation($second, $assessment, $scenario['version'], COMMITTEE_TOP_MARKS)->assertOk()->assertJsonPath('data.percent', '100.00');

    $assessment->refresh();
    expect($assessment->status->value)->toBe('awaiting_secretary_approval')
        ->and(CommitteeAverage::query()->where('assessment_id', $assessment->id)->sole()->percent->toDisplay())->toBe('88.50')
        ->and($assessment->committee_average_id)->not->toBeNull()
        ->and(noticeCodesFor($scenario['secretary']))->toContain(NotificationCode::CommitteeAllSubmitted->value)
        ->and($scenario['cycle']->refresh()->status)->toBe(AwardCycleStatus::AwaitingSecretary);
});

it('keeps committee work away from other assessors, other roles and closed stages', function (): void {
    $scenario = committeeScenario(1, 1);
    $assessment = $scenario['requests'][0];
    $row = committeeRowOf($scenario['assessors'][0], $assessment);

    switchTo(assessorUser())->getJson(myAssignmentPath($row))->assertNotFound();
    test()->putJson(myAssignmentPath($row, 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '7'])])->assertNotFound();
    test()->getJson('/api/v1/committee/me/assignments')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo($scenario['secretary'])->getJson('/api/v1/committee/me/assignments')->assertForbidden();
    switchTo(ownerOf($assessment->entity))->getJson('/api/v1/committee/me/assignments')->assertForbidden();
    switchTo(ownerOf($assessment->entity))->getJson(myAssignmentPath($row))->assertNotFound();
});

it('refuses committee writes on a frozen request', function (): void {
    $scenario = committeeScenario(1, 1);
    $assessment = $scenario['requests'][0];
    $row = committeeRowOf($scenario['assessors'][0], $assessment);
    $assessment->entity->delete();

    switchTo($scenario['assessors'][0])->putJson(myAssignmentPath($row, 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '7'])])->assertConflict()->assertJsonPath('code', 'entity.deleted');
    test()->getJson('/api/v1/committee/me/assignments')->assertOk()->assertJsonPath('meta.pagination.total', 0);
});

it('alerts overdue committee assessors once after the committee end date', function (): void {
    $scenario = committeeScenario(2, 1);
    $assessor = $scenario['assessors'][0];
    submitCommitteeEvaluation($assessor, $scenario['requests'][0], $scenario['version'])->assertOk();
    Date::setTestNow($scenario['cycle']->committee_end->copy()->addDay());

    Artisan::call('moashir:award:flag-overdue-committee');
    Artisan::call('moashir:award:flag-overdue-committee');

    expect(array_count_values(noticeCodesFor($assessor))[NotificationCode::CommitteeOverdue->value] ?? 0)->toBe(1)
        ->and(noticeCodesFor($scenario['secretary']))->toContain(NotificationCode::CommitteeOverdue->value);
    switchTo($assessor)->getJson('/api/v1/committee/me/assignments?filter[status]=not_started')->assertOk()->assertJsonPath('data.0.is_overdue', true);
});

it('lets a seated committee assessor download the clean evidence of the request and nobody outside the committee', function (): void {
    $scenario = committeeScenario(1, 1);
    $assessment = $scenario['requests'][0];
    $assessor = $scenario['assessors'][0];
    $evidenceId = switchTo($assessor)->getJson(myAssignmentPath(committeeRowOf($assessor, $assessment)))->assertOk()->json('data.evidence.0.id');

    $url = test()->postJson("/api/v1/assessments/{$assessment->id}/evidence/{$evidenceId}/download")->assertOk()->json('data.url');
    test()->get($url)->assertOk();
    switchTo(assessorUser())->postJson("/api/v1/assessments/{$assessment->id}/evidence/{$evidenceId}/download")->assertNotFound();
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}")->assertNotFound();
    test()->getJson("/api/v1/assessments/{$assessment->id}/result")->assertNotFound();
});
