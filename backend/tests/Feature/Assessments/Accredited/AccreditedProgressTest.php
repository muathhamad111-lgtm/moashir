<?php

declare(strict_types=1);

use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const ACCREDITED_CURRENT = '/api/v1/assessments/accredited/current';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('reports the submission progress of a fresh accredited draft with the remaining sub-criteria by code', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $policy = subOf($version, 'M1.1');
    $strategy = subOf($version, 'M1.2');

    switchTo(ownerOf($assessment->entity))->getJson(ACCREDITED_CURRENT)->assertOk()
        ->assertJsonPath('data.active.id', $assessment->id)
        ->assertJsonPath('data.active.progress', [
            'subs_total' => 3,
            'subs_complete' => 1,
            'remaining' => [
                ['sub_criterion_id' => $policy->id, 'code' => 'M1.1', 'name' => $policy->name->toArray(), 'cv_missing' => true, 'attachments_required' => 1, 'attachments_uploaded' => 0],
                ['sub_criterion_id' => $strategy->id, 'code' => 'M1.2', 'name' => $strategy->name->toArray(), 'cv_missing' => true, 'attachments_required' => 0, 'attachments_uploaded' => 0],
            ],
        ]);
});

it('counts a CV-only sub-criterion as complete once its CV is saved and keeps the evidence gap of a mixed one', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $strategyId = (string) subOf($version, 'M1.2')->id;

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [$strategyId => ACCREDITED_CV_TEXT]])->assertOk();
    uploadEvidence($owner, $assessment, $version, 'M1.1', 'board_minutes')->assertCreated();

    switchTo($owner)->getJson(ACCREDITED_CURRENT)->assertOk()
        ->assertJsonPath('data.active.progress.subs_complete', 2)
        ->assertJsonCount(1, 'data.active.progress.remaining')
        ->assertJsonPath('data.active.progress.remaining.0.code', 'M1.1')
        ->assertJsonPath('data.active.progress.remaining.0.cv_missing', true)
        ->assertJsonPath('data.active.progress.remaining.0.attachments_uploaded', 0);
});

it('agrees with the submission rules once every gap is closed', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = readyAccreditedDraft($version);
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->getJson(ACCREDITED_CURRENT)->assertOk()
        ->assertJsonPath('data.active.progress', ['subs_total' => 3, 'subs_complete' => 3, 'remaining' => []]);
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.missing', ['missing_cv' => [], 'missing_attachments' => []]);
});

it('hides the progress once the request has been submitted', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);

    switchTo(ownerOf($assessment->entity))->getJson(ACCREDITED_CURRENT)->assertOk()
        ->assertJsonPath('data.active.status', 'submitted')
        ->assertJsonPath('data.active.progress', null);
});

it('counts only the requested sub-criteria while the request needs completion', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $owner = ownerOf($assessment->entity);
    $policy = subOf($version, 'M1.1');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى تحديث وصف السياسة المعتمدة وإرفاق نسختها الموقعة.',
        'items' => [['sub_criterion_id' => $policy->id, 'kind' => 'cv'], ['sub_criterion_id' => $policy->id, 'kind' => 'evidence']],
        'deadline_at' => Date::now()->addDays(7)->toIso8601String(),
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    switchTo($owner)->getJson(ACCREDITED_CURRENT)->assertOk()
        ->assertJsonPath('data.active.status', 'needs_completion')
        ->assertJsonPath('data.active.progress', ['subs_total' => 1, 'subs_complete' => 1, 'remaining' => []]);

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) $policy->id => null]])->assertOk();

    switchTo($owner)->getJson(ACCREDITED_CURRENT)->assertOk()
        ->assertJsonPath('data.active.progress.subs_complete', 0)
        ->assertJsonPath('data.active.progress.remaining.0.code', 'M1.1')
        ->assertJsonPath('data.active.progress.remaining.0.cv_missing', true);
});

it('reads the progress with a number of queries that does not grow with the evidence', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    uploadEvidence($owner, $assessment, $version, 'M1.1', 'board_minutes')->assertCreated();
    $before = currentRequestQueryCount($owner);

    uploadEvidence($owner, $assessment, $version)->assertCreated();
    uploadEvidence($owner, $assessment, $version, 'M1.1', null)->assertCreated();
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertOk();

    expect(currentRequestQueryCount($owner))->toBe($before);
});

function currentRequestQueryCount(User $owner): int
{
    switchTo($owner)->getJson(ACCREDITED_CURRENT)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    switchTo($owner)->getJson(ACCREDITED_CURRENT)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}
