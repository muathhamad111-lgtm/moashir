<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Models\Notifications\Notification as InAppNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

function accreditedOnlyAssessor(): User
{
    return qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::Accredited);
}

function advancedOnlyAssessor(): User
{
    return qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::AdvancedInitial);
}

describe('assessor qualification', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('lists only the assessors qualified for the path of the request', function (): void {
        $version = publishedAccreditedCriteria();
        $assessment = submittedAccreditedRequest($version);
        $accredited = accreditedOnlyAssessor();
        $advanced = advancedOnlyAssessor();
        $unqualified = unqualifiedStaffUser(RoleKey::Assessor);
        $follower = requestFollower();

        $ids = collect(switchTo($follower)->getJson("/api/v1/requests/assessors?assessment_id={$assessment->id}")->assertOk()->json('data'))->pluck('id');

        expect($ids->all())->toContain($accredited->id)->not->toContain($advanced->id, $unqualified->id);

        $advancedIds = collect(test()->getJson('/api/v1/requests/assessors?path=advanced')->assertOk()->json('data'))->pluck('id');
        $defaultIds = collect(test()->getJson('/api/v1/requests/assessors')->assertOk()->json('data'))->pluck('id');

        expect($advancedIds->all())->toContain($advanced->id)->not->toContain($accredited->id, $unqualified->id)
            ->and($defaultIds->all())->toContain($accredited->id)->not->toContain($advanced->id);

        test()->getJson('/api/v1/requests/assessors?path=self')->assertUnprocessable()->assertJsonValidationErrors(['path']);
        test()->getJson('/api/v1/requests/assessors?assessment_id=999999')->assertUnprocessable()->assertJsonValidationErrors(['assessment_id']);
    });

    it('refuses to assign or reassign an assessor who is not qualified for the path', function (): void {
        $version = publishedAccreditedCriteria();
        $assessment = submittedAccreditedRequest($version);
        $follower = requestFollower();

        switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => advancedOnlyAssessor()->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'assessor.not_qualified')
            ->assertJsonPath('meta.required_qualification', 'accredited')
            ->assertJsonPath('meta.path', 'accredited')
            ->assertJsonValidationErrors(['assessor_id']);
        test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => unqualifiedStaffUser(RoleKey::Assessor)->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'assessor.not_qualified');

        expect(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->count())->toBe(0);

        test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => accreditedOnlyAssessor()->id])->assertOk();
        test()->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => advancedOnlyAssessor()->id, 'reason' => 'إعادة الإسناد لتوازن الأعباء'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'assessor.not_qualified');
    });

    it('keeps the open assignment when a qualification is removed but refuses new assignments', function (): void {
        $version = publishedAccreditedCriteria();
        $assessor = accreditedOnlyAssessor();
        $assessment = assignedAccreditedRequest($version, $assessor);
        $other = submittedAccreditedRequest($version);

        switchTo(systemAdmin())->putJson("/api/v1/users/{$assessor->id}/assessor-qualifications", ['qualifications' => []])->assertOk();

        switchTo($assessor->refresh())->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk();
        switchTo(requestFollower())->postJson("/api/v1/requests/{$other->id}/assign", ['assessor_id' => $assessor->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'assessor.not_qualified');
    });

    it('requires the advanced initial qualification from cycle initial assessors', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        $accredited = accreditedOnlyAssessor();
        $advanced = advancedOnlyAssessor();
        $admin = systemAdmin();

        $candidates = collect(switchTo($admin)->getJson('/api/v1/award/cycles/candidates?role=initial_assessor')->assertOk()->json('data'))->pluck('id');

        expect($candidates->all())->toContain($advanced->id)->not->toContain($accredited->id);

        test()->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => [$accredited->id]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'assessor.not_qualified')
            ->assertJsonValidationErrors(['assessor_ids.0']);
        test()->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => [$advanced->id]])->assertOk();

        $assessment = submittedAdvancedRequest($cycle, $version);
        linkInitialAssessor($cycle, $accredited);

        switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $accredited->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'assessor.not_qualified')
            ->assertJsonPath('meta.required_qualification', 'advanced_initial');
        test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $advanced->id])->assertOk();
    });
});

describe('follower path scope', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('shows a scoped follower only the queue of the followed path', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        $accredited = submittedAccreditedRequest($version);
        $advanced = submittedAdvancedRequest($cycle, $version);
        $follower = followerOfPaths(AssessmentPath::Accredited);

        switchTo($follower)->getJson('/api/v1/requests')->assertOk()
            ->assertJsonPath('meta.path', 'accredited')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $accredited->id)
            ->assertJsonPath('data.0.path_label.en', 'Accredited assessment')
            ->assertJsonCount(1, 'meta.path_counts')
            ->assertJsonPath('meta.path_counts.0.path', 'accredited');
        test()->getJson('/api/v1/requests?filter[path]=advanced')->assertUnprocessable()->assertJsonValidationErrors(['filter.path']);
        test()->getJson("/api/v1/requests?filter[cycle_id]={$cycle->id}")->assertUnprocessable()->assertJsonValidationErrors(['filter.cycle_id']);
        test()->getJson("/api/v1/requests/{$accredited->id}")->assertOk();
        test()->getJson("/api/v1/requests/{$advanced->id}")->assertNotFound();
        test()->getJson("/api/v1/requests/assessors?assessment_id={$advanced->id}")->assertUnprocessable()->assertJsonValidationErrors(['assessment_id']);
    });

    it('refuses a scoped follower every action on a request of another path', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        $assessor = assessorUser();
        linkInitialAssessor($cycle, $assessor);
        $advanced = submittedAdvancedRequest($cycle, $version);

        switchTo(followerOfPaths(AssessmentPath::Accredited));
        test()->postJson("/api/v1/requests/{$advanced->id}/assign", ['assessor_id' => $assessor->id])->assertNotFound();
        test()->postJson("/api/v1/requests/{$advanced->id}/withdraw", ['reason' => 'سحب الطلب بناء على طلب الجهة'])->assertNotFound();
        test()->postJson("/api/v1/requests/{$advanced->id}/return-for-completion", [])->assertNotFound();
        test()->getJson("/api/v1/assessments/{$advanced->id}")->assertNotFound();
        test()->getJson("/api/v1/assessments/{$advanced->id}/form")->assertNotFound();
        test()->getJson("/api/v1/award/cycles/{$cycle->id}")->assertNotFound();

        expect($advanced->refresh()->status->value)->toBe('awaiting_review_assignment');

        switchTo(followerOfPaths(AssessmentPath::Advanced));
        test()->getJson("/api/v1/requests/{$advanced->id}")->assertOk();
        test()->getJson("/api/v1/award/cycles/{$cycle->id}")->assertOk();
        test()->postJson("/api/v1/requests/{$advanced->id}/assign", ['assessor_id' => $assessor->id])->assertOk();
    });

    it('hides the requests of other paths from the global search of a scoped follower', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        $accredited = submittedAccreditedRequest($version);
        $advanced = submittedAdvancedRequest($cycle, $version);

        switchTo(followerOfPaths(AssessmentPath::Accredited));
        $found = static fn (string $number): int => (int) (collect(test()->getJson('/api/v1/search?q='.urlencode(strtolower($number)))->assertOk()->json('data'))->firstWhere('type', 'requests')['total'] ?? 0);

        expect($found($accredited->request_number))->toBe(1)
            ->and($found($advanced->request_number))->toBe(0);
    });

    it('counts only the followed paths on the follower dashboard and Action Center', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        submittedAccreditedRequest($version);
        submittedAdvancedRequest($cycle, $version);
        submittedAdvancedRequest($cycle, $version);

        $scoped = boardOf(followerOfPaths(AssessmentPath::Accredited), 'request_follower');
        $global = boardOf(requestFollower(), 'request_follower');

        expect(boardCards($scoped)->get('awaiting_assignment'))->toBe(1)
            ->and(boardWidget($scoped, 'requests_by_path'))->toBe([['path' => 'accredited', 'path_label' => ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'], 'count' => 1]])
            ->and(actionItems($scoped)->get('assign_requests')['count'] ?? null)->toBe(1)
            ->and(boardCards($global)->get('awaiting_assignment'))->toBe(3)
            ->and(actionItems($global)->get('assign_requests')['count'] ?? null)->toBe(3)
            ->and(collect(boardWidget($global, 'requests_by_path'))->pluck('count', 'path')->all())->toBe(['accredited' => 1, 'advanced' => 2]);
    });
});

describe('separate queues and counts per path', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('returns the per-path counts of the queue for the sidebar', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        submittedAccreditedRequest($version);
        $overdue = assignedAccreditedRequest($version, assessorUser());
        $overdue->forceFill(['due_at' => Date::now()->subDay()])->save();
        submittedAdvancedRequest($cycle, $version);
        openedAccreditedRequest();

        switchTo(requestFollower())->getJson('/api/v1/requests?filter[status]=assigned')->assertOk()
            ->assertJsonPath('meta.path', 'accredited')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.path_counts', [
                ['path' => 'accredited', 'path_label' => ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'], 'total' => 2, 'awaiting_assignment' => 1, 'overdue' => 1, 'preparing' => 1],
                ['path' => 'advanced', 'path_label' => ['ar' => 'التقييم المتقدم (الجائزة)', 'en' => 'Advanced assessment (Award)'], 'total' => 1, 'awaiting_assignment' => 1, 'overdue' => 0, 'preparing' => 0],
            ]);
    });

    it('serves the assigned list per path with per-path tab counts', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        $assessor = assessorUser();
        $accredited = assignedAccreditedRequest($version, $assessor);
        $advanced = assignedAdvancedRequest($cycle, $version, $assessor);
        switchTo($assessor)->postJson("/api/v1/assessments/{$advanced->id}/start-review")->assertOk();

        switchTo($assessor)->getJson('/api/v1/requests/assigned')->assertOk()
            ->assertJsonPath('meta.path', 'accredited')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $accredited->id)
            ->assertJsonPath('meta.path_counts.0', ['path' => 'accredited', 'path_label' => ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'], 'new' => 1, 'in_progress' => 0, 'completed' => 0])
            ->assertJsonPath('meta.path_counts.1.in_progress', 1);
        test()->getJson('/api/v1/requests/assigned?filter[path]=advanced')->assertOk()
            ->assertJsonPath('meta.path', 'advanced')
            ->assertJsonPath('data.0.id', $advanced->id)
            ->assertJsonPath('data.0.path_label.en', 'Advanced assessment (Award)');
        test()->getJson('/api/v1/requests/assigned?filter[path]=self')->assertUnprocessable();
    });

    it('defaults the assigned list of an assessor qualified for the advanced path only', function (): void {
        $assessor = advancedOnlyAssessor();

        switchTo($assessor)->getJson('/api/v1/requests/assigned')->assertOk()
            ->assertJsonPath('meta.path', 'advanced')
            ->assertJsonPath('meta.path_counts', [['path' => 'advanced', 'path_label' => ['ar' => 'التقييم المتقدم (الجائزة)', 'en' => 'Advanced assessment (Award)'], 'new' => 0, 'in_progress' => 0, 'completed' => 0]]);
    });
});

describe('notifications per path', function (): void {
    it('stores the path on request notifications and sends them only to the followers of that path', function (): void {
        $version = publishedAccreditedCriteria();
        $cycle = openAwardCycle($version);
        $accreditedFollower = followerOfPaths(AssessmentPath::Accredited);
        $advancedFollower = followerOfPaths(AssessmentPath::Advanced);
        $globalFollower = requestFollower();

        submittedAccreditedRequest($version);
        submittedAdvancedRequest($cycle, $version);

        $paths = static fn (int $userId): array => InAppNotification::query()->where('user_id', $userId)->whereNotNull('path')->distinct()->orderBy('path')->pluck('path')->map->value->all();

        expect($paths($accreditedFollower->id))->toBe(['accredited'])
            ->and($paths($advancedFollower->id))->toBe(['advanced'])
            ->and($paths($globalFollower->id))->toBe(['accredited', 'advanced']);

        switchTo($accreditedFollower)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.path', 'accredited')
            ->assertJsonPath('data.0.path_label', ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment']);
        test()->getJson('/api/v1/notifications?filter[path]=advanced')->assertOk()->assertJsonPath('meta.pagination.total', 0);
        test()->getJson('/api/v1/notifications?filter[path]=leadership')->assertUnprocessable()->assertJsonValidationErrors(['filter.path']);
    });
});
