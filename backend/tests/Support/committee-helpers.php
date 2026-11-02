<?php

declare(strict_types=1);

use App\Enums\Award\CycleMemberRole;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use Illuminate\Testing\TestResponse;

const COMMITTEE_TOP_MARKS = ['M1.1' => '10', 'M1.2.1' => '10', 'M1.2.2' => '10', 'M2.1' => '10'];

const COMMITTEE_MID_MARKS = ['M1.1' => '6', 'M1.2.1' => '6', 'M1.2.2' => '6', 'M2.1' => '6'];

const COMMITTEE_DISABLE_REASON = 'اعتذر المقيّم عن إكمال أعمال اللجنة لظرف صحي طارئ موثق.';

const COMMITTEE_RETURN_NOTE = 'يرجى مراجعة درجات معيار القيادة في ضوء الشواهد المرفقة قبل الاعتماد.';

const UNAPPROVE_REASON = 'إعادة فتح ترتيب المنطقة بعد ثبوت خطأ إداري في إدخال المراكز بخطاب رسمي.';

/**
 * @return array{0: AwardCycle, 1: CriteriaVersion, 2: User, 3: User}
 */
function committeeCycle(?Region $region = null): array
{
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version, [], $region);
    $secretary = awardSecretary();
    $officer = filteringOfficer();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    return [$cycle, $version, $secretary, $officer];
}

function qualifyRequest(AwardCycle $cycle, User $officer, Assessment $assessment): void
{
    switchTo($officer)->postJson("/api/v1/award/cycles/{$cycle->id}/filtering/{$assessment->id}/qualify")->assertOk();
}

/**
 * @return list<User>
 */
function appointCommittee(AwardCycle $cycle, User $manager, int $count): array
{
    $assessors = [];

    for ($index = 0; $index < $count; $index++) {
        $assessor = assessorUser();
        switchTo($manager)->postJson(committeePath($cycle, 'members'), ['user_id' => $assessor->id])->assertCreated();
        $assessors[] = $assessor;
    }

    return $assessors;
}

function startCommittee(AwardCycle $cycle, User $manager): TestResponse
{
    return switchTo($manager)->postJson("/api/v1/award/cycles/{$cycle->id}/start-committee");
}

function committeePath(AwardCycle $cycle, string $suffix = ''): string
{
    return "/api/v1/award/cycles/{$cycle->id}/committee".($suffix === '' ? '' : '/'.$suffix);
}

function secretaryPath(AwardCycle $cycle, ?Assessment $assessment = null, string $suffix = ''): string
{
    $path = "/api/v1/award/cycles/{$cycle->id}/secretary/requests";

    if ($assessment instanceof Assessment) {
        $path .= '/'.$assessment->id;
    }

    return $suffix === '' ? $path : $path.'/'.$suffix;
}

function seatOf(AwardCycle $cycle, User $assessor): CycleCommitteeAssessor
{
    return CycleCommitteeAssessor::query()->where('award_cycle_id', $cycle->id)->where('assessor_user_id', $assessor->id)->seated()->firstOrFail();
}

function committeeRowOf(User $assessor, Assessment $assessment): CommitteeAssignment
{
    return CommitteeAssignment::query()
        ->where('assessment_id', $assessment->id)
        ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->where('assessor_user_id', $assessor->id))
        ->latest('id')
        ->firstOrFail();
}

function myAssignmentPath(CommitteeAssignment $assignment, string $suffix = ''): string
{
    return "/api/v1/committee/me/assignments/{$assignment->id}".($suffix === '' ? '' : '/'.$suffix);
}

/**
 * @param  array<string, string>  $marks
 */
function submitCommitteeEvaluation(User $assessor, Assessment $assessment, CriteriaVersion $version, array $marks = ACCREDITED_FULL_MARKS): TestResponse
{
    $row = committeeRowOf($assessor, $assessment);
    switchTo($assessor)->putJson(myAssignmentPath($row, 'scores'), ['scores' => selfScores($version, $marks)])->assertOk();

    return test()->postJson(myAssignmentPath($row, 'submit'));
}

/**
 * @return array{cycle: AwardCycle, version: CriteriaVersion, secretary: User, officer: User, assessors: list<User>, requests: list<Assessment>}
 */
function committeeScenario(int $requests = 1, int $assessors = 2, ?Region $region = null): array
{
    [$cycle, $version, $secretary, $officer] = committeeCycle($region);
    $created = [];

    for ($index = 0; $index < $requests; $index++) {
        $created[] = initiallyAssessedRequest($cycle, $version, assessorUser());
    }

    closeCycleSubmissions($cycle);

    foreach ($created as $assessment) {
        qualifyRequest($cycle, $officer, $assessment);
    }

    $committee = appointCommittee($cycle, $secretary, $assessors);
    startCommittee($cycle, $secretary)->assertOk();

    return [
        'cycle' => $cycle->refresh(),
        'version' => $version,
        'secretary' => $secretary,
        'officer' => $officer,
        'assessors' => $committee,
        'requests' => array_map(static fn (Assessment $assessment): Assessment => $assessment->refresh(), $created),
    ];
}

/**
 * @param  array{cycle: AwardCycle, version: CriteriaVersion, secretary: User, officer: User, assessors: list<User>, requests: list<Assessment>}  $scenario
 * @param  array<string, string>  $marks
 */
function approvedByCommittee(array $scenario, Assessment $assessment, array $marks = ACCREDITED_FULL_MARKS): Assessment
{
    foreach ($scenario['assessors'] as $assessor) {
        submitCommitteeEvaluation($assessor, $assessment, $scenario['version'], $marks)->assertOk();
    }

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'approve'))->assertOk()->assertJsonPath('data.status', 'approved');

    return $assessment->refresh();
}
