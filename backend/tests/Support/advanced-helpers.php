<?php

declare(strict_types=1);

use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

const RETURN_NOTES = 'يرجى إرفاق محضر اعتماد مجلس الإدارة للسياسة وتحديث وصفها في السيرة.';

const WITHDRAWAL_REASON = 'طلبت الجهة سحب طلبها في الجائزة بخطاب رسمي من مجلس الإدارة.';

const FILTERING_OVERRIDE_REASON = 'أظهرت الجهة تحسنًا موثقًا في معيار القيادة يبرر تأهيلها استثناءً لمرحلة تقييم اللجنة.';

const DISQUALIFY_REASON = 'لم تبلغ نتيجة التقييم الأولي الحد الأدنى المعتمد للتأهل في هذه الدورة.';

function awardSecretary(): User
{
    return staffUser(RoleKey::AwardSecretary);
}

function filteringOfficer(): User
{
    return staffUser(RoleKey::FilteringOfficer);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function draftAwardCycle(CriteriaVersion $version, array $attributes = [], ?Region $region = null): AwardCycle
{
    $cycle = AwardCycle::factory()->boundTo($version)->create($attributes);
    $cycle->regions()->sync([($region ?? Region::factory()->create())->id]);

    return $cycle->refresh()->load('regions');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function openAwardCycle(CriteriaVersion $version, array $attributes = [], ?Region $region = null): AwardCycle
{
    $now = Date::now();

    return draftAwardCycle($version, [
        'status' => AwardCycleStatus::SubmissionOpen,
        'opened_at' => $now->copy()->subDay(),
        'submission_start' => $now->copy()->subDay(),
        'submission_end' => $now->copy()->addDays(20),
        'committee_start' => $now->copy()->addDays(30),
        'committee_end' => $now->copy()->addDays(60),
        'announcement_at' => $now->copy()->addDays(70),
        ...$attributes,
    ], $region);
}

function closeCycleSubmissions(AwardCycle $cycle): AwardCycle
{
    $cycle->forceFill([
        'status' => AwardCycleStatus::SubmissionClosed,
        'submission_end' => Date::now()->subMinute(),
        'submissions_closed_at' => Date::now(),
    ])->save();

    return $cycle->refresh();
}

function cycleEntity(AwardCycle $cycle, int $foundingYear = 2015): Entity
{
    $regionId = $cycle->regions->firstOrFail()->id;

    return entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['region_id' => $regionId, 'founding_year' => $foundingYear]));
}

function applyForCycle(User $owner, AwardCycle $cycle): TestResponse
{
    return switchTo($owner)->postJson('/api/v1/assessments/advanced', ['award_cycle_id' => $cycle->id]);
}

function advancedDraft(AwardCycle $cycle, ?Entity $entity = null): Assessment
{
    $id = applyForCycle(ownerOf($entity ?? cycleEntity($cycle)), $cycle)->assertCreated()->json('data.id');

    return Assessment::query()->findOrFail($id);
}

function readyAdvancedDraft(AwardCycle $cycle, CriteriaVersion $version, ?Entity $entity = null): Assessment
{
    $draft = advancedDraft($cycle, $entity);
    $owner = ownerOf($draft->entity);

    switchTo($owner)->putJson("/api/v1/assessments/{$draft->id}/cv", ['cv' => accreditedCv($version)])->assertOk();
    uploadEvidence($owner, $draft, $version)->assertCreated();

    return $draft->refresh();
}

function submittedAdvancedRequest(AwardCycle $cycle, CriteriaVersion $version, ?Entity $entity = null): Assessment
{
    $draft = readyAdvancedDraft($cycle, $version, $entity);

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")->assertOk()->assertJsonPath('data.status', 'awaiting_review_assignment');

    return $draft->refresh();
}

function linkInitialAssessor(AwardCycle $cycle, User $assessor): void
{
    AwardCycleAssessor::query()->updateOrCreate(['award_cycle_id' => $cycle->id, 'assessor_user_id' => $assessor->id], ['is_active' => true]);
}

function addCycleMember(AwardCycle $cycle, User $user, CycleMemberRole $role): void
{
    AwardCycleMember::query()->create(['award_cycle_id' => $cycle->id, 'user_id' => $user->id, 'role' => $role]);
}

function assignedAdvancedRequest(AwardCycle $cycle, CriteriaVersion $version, User $assessor, ?User $follower = null): Assessment
{
    linkInitialAssessor($cycle, $assessor);
    $assessment = submittedAdvancedRequest($cycle, $version);

    switchTo($follower ?? requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertOk()->assertJsonPath('data.status', 'assigned');

    return $assessment->refresh();
}

function startedInitialAssessment(AwardCycle $cycle, CriteriaVersion $version, User $assessor): Assessment
{
    $assessment = assignedAdvancedRequest($cycle, $version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk()->assertJsonPath('data.status', 'under_initial_assessment');

    return $assessment->refresh();
}

/**
 * @param  array<string, string>  $marks
 */
function initiallyAssessedRequest(AwardCycle $cycle, CriteriaVersion $version, User $assessor, array $marks = ACCREDITED_FULL_MARKS): Assessment
{
    $assessment = startedInitialAssessment($cycle, $version, $assessor);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, $marks)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'awaiting_filtering');

    return $assessment->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cyclePayload(CriteriaVersion $version, Region $region, array $overrides = []): array
{
    return [
        'name' => ['ar' => 'دورة جائزة التميز المؤسسي 2026', 'en' => 'Institutional Excellence Award 2026'],
        'submission_start' => '2026-11-05T06:00:00+00:00',
        'submission_end' => '2026-12-05T20:59:00+00:00',
        'committee_start' => '2026-12-20T06:00:00+00:00',
        'committee_end' => '2027-01-20T20:59:00+00:00',
        'announcement_at' => '2027-02-01T06:00:00+00:00',
        'region_ids' => [$region->id],
        'criteria_version_id' => $version->id,
        ...$overrides,
    ];
}
