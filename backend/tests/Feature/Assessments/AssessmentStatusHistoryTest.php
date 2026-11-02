<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const STATUS_HISTORY_ITEM_KEYS = ['from', 'to', 'verb', 'at', 'actor'];

const STATUS_HISTORY_STAFF_NAME = 'نورة عبدالله القحطاني';

const STATUS_HISTORY_INTERNAL_REASON = 'مراجعة داخلية لطلب الجهة قبل إحالته إلى لجنة المقيّمين.';

const STATUS_HISTORY_OVERRIDE_REASON = 'طلبت الجهة إرسال التقييم نيابةً عنها عبر خطاب رسمي معتمد.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

function submittedByOwner(): Assessment
{
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    Date::setTestNow('2026-11-01 08:30:00');
    test()->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    return $draft;
}

function staffTransitionOn(Assessment $assessment, User $staff): void
{
    AssessmentStatusTransition::query()->create([
        'assessment_id' => $assessment->id,
        'from_status' => 'draft',
        'to_status' => 'draft',
        'verb' => AssessmentVerb::SaveDraft,
        'actor_user_id' => $staff->id,
        'reason' => STATUS_HISTORY_INTERNAL_REASON,
    ]);
}

function namedRequestFollower(): User
{
    $follower = staffUser(RoleKey::RequestFollower);
    $follower->forceFill(['name' => STATUS_HISTORY_STAFF_NAME])->save();

    return $follower->refresh();
}

/**
 * @return array{key: string, label: array{ar: string, en: string}}
 */
function roleSummary(RoleKey $role): array
{
    return ['key' => $role->value, 'label' => ['ar' => __("roles.{$role->value}.label", [], 'ar'), 'en' => __("roles.{$role->value}.label", [], 'en')]];
}

it('shows the entity its own status history, oldest first, without any staff identity', function (): void {
    $assessment = submittedByOwner();

    $response = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'result_available')
        ->assertJsonPath('data.status_history', [
            ['from' => null, 'to' => 'draft', 'verb' => 'start', 'at' => '2026-11-01T08:00:00+00:00', 'actor' => 'entity'],
            ['from' => 'draft', 'to' => 'submitted', 'verb' => 'submit', 'at' => '2026-11-01T08:30:00+00:00', 'actor' => 'entity'],
            ['from' => 'submitted', 'to' => 'result_available', 'verb' => 'compute_result', 'at' => '2026-11-01T08:30:00+00:00', 'actor' => 'system'],
        ]);

    expect(array_keys($response->json('data.status_history.0')))->toBe(STATUS_HISTORY_ITEM_KEYS)
        ->and($response->getContent())->not->toContain('actor_staff');
});

it('names the staff member and roles behind a transition for staff readers only', function (): void {
    $assessment = submittedByOwner();
    $follower = namedRequestFollower();
    staffTransitionOn($assessment, $follower);

    $staffView = switchTo(systemAdmin())->getJson("/api/v1/assessments/{$assessment->id}")
        ->assertOk()
        ->assertJsonPath('data.status_history.0.actor', 'entity')
        ->assertJsonPath('data.status_history.0.actor_staff', null)
        ->assertJsonPath('data.status_history.2.actor', 'system')
        ->assertJsonPath('data.status_history.2.actor_staff', null)
        ->assertJsonPath('data.status_history.3.actor', 'staff')
        ->assertJsonPath('data.status_history.3.actor_staff', ['name' => STATUS_HISTORY_STAFF_NAME, 'roles' => [roleSummary(RoleKey::RequestFollower)]]);

    expect(array_keys($staffView->json('data.status_history.3')))->toBe([...STATUS_HISTORY_ITEM_KEYS, 'actor_staff'])
        ->and($staffView->getContent())->not->toContain($follower->email)
        ->and($staffView->getContent())->not->toContain(STATUS_HISTORY_INTERNAL_REASON);

    $ownView = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")
        ->assertOk()
        ->assertJsonPath('data.status_history.3.actor', 'staff');

    expect(array_keys($ownView->json('data.status_history.3')))->toBe(STATUS_HISTORY_ITEM_KEYS)
        ->and($ownView->getContent())->not->toContain(STATUS_HISTORY_STAFF_NAME)
        ->and($ownView->getContent())->not->toContain($follower->email)
        ->and($ownView->getContent())->not->toContain('actor_staff')
        ->and($ownView->getContent())->not->toContain(STATUS_HISTORY_INTERNAL_REASON);
});

it('shows a submission made by the Super Admin as staff, without its identity or reason', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $superAdmin = superAdmin();
    switchTo($superAdmin)->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10), 'reason' => STATUS_HISTORY_OVERRIDE_REASON])->assertOk();

    $ownView = switchTo(ownerOf($draft->entity))->getJson("/api/v1/assessments/{$draft->id}")
        ->assertOk()
        ->assertJsonPath('data.status_history.1.actor', 'staff');

    expect($ownView->getContent())->not->toContain($superAdmin->name)
        ->and($ownView->getContent())->not->toContain(STATUS_HISTORY_OVERRIDE_REASON)
        ->and($ownView->getContent())->not->toContain('actor_staff');

    $staffView = switchTo(staffUser(RoleKey::RequestFollower))->getJson("/api/v1/assessments/{$draft->id}")
        ->assertOk()
        ->assertJsonPath('data.status_history.1.actor', 'staff')
        ->assertJsonPath('data.status_history.1.actor_staff', ['name' => null, 'roles' => []]);

    expect($staffView->getContent())->not->toContain($superAdmin->name)
        ->and($staffView->getContent())->not->toContain(STATUS_HISTORY_OVERRIDE_REASON);
});

it('shows no name and no roles for a staff member who no longer exists', function (): void {
    $assessment = submittedByOwner();
    $follower = namedRequestFollower();
    staffTransitionOn($assessment, $follower);
    $follower->delete();

    $response = switchTo(systemAdmin())->getJson("/api/v1/assessments/{$assessment->id}")
        ->assertOk()
        ->assertJsonPath('data.status_history.3.actor_staff', ['name' => null, 'roles' => []]);

    expect($response->getContent())->not->toContain(STATUS_HISTORY_STAFF_NAME);
});

it('gives every global reader, the Super Admin included, the staff view of the history', function (string $reader): void {
    $assessment = submittedByOwner();
    staffTransitionOn($assessment, namedRequestFollower());
    $viewer = match ($reader) {
        'super admin' => superAdmin(),
        'requests follower' => staffUser(RoleKey::RequestFollower),
        default => systemAdmin(),
    };

    switchTo($viewer)->getJson("/api/v1/assessments/{$assessment->id}")
        ->assertOk()
        ->assertJsonCount(4, 'data.status_history')
        ->assertJsonPath('data.status_history.3.actor_staff.name', STATUS_HISTORY_STAFF_NAME);
})->with(['system admin', 'requests follower', 'super admin']);

it('keeps the history behind the record policy', function (string $viewer, int $status): void {
    $assessment = submittedByOwner();
    staffTransitionOn($assessment, namedRequestFollower());

    $response = switchTo(match ($viewer) {
        'another entity' => ownerOf(entityWithOwner()),
        'assessor' => staffUser(RoleKey::Assessor),
        'criteria officer' => staffUser(RoleKey::CriteriaOfficer),
        default => staffUser(RoleKey::FinanceOfficer),
    })->getJson("/api/v1/assessments/{$assessment->id}")->assertStatus($status);

    expect($response->getContent())->not->toContain(STATUS_HISTORY_STAFF_NAME)
        ->and($response->getContent())->not->toContain('status_history');
})->with([
    'another entity' => ['another entity', 404],
    'assessor outside the scope' => ['assessor', 404],
    'criteria officer' => ['criteria officer', 403],
    'finance officer' => ['finance officer', 403],
]);

it('asks guests to sign in before reading the history', function (): void {
    $assessment = submittedByOwner();
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->getJson("/api/v1/assessments/{$assessment->id}")->assertUnauthorized();
});

it('reads the history and its staff actors with a fixed number of queries', function (): void {
    $assessment = submittedByOwner();
    staffTransitionOn($assessment, namedRequestFollower());
    $laterActors = [staffUser(RoleKey::SystemAdmin), staffUser(RoleKey::RequestFollower)];
    switchTo(systemAdmin())->getJson("/api/v1/assessments/{$assessment->id}")->assertOk();

    $queriesFor = function () use ($assessment): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/v1/assessments/{$assessment->id}")->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $withOneStaffActor = $queriesFor();

    foreach ($laterActors as $actor) {
        staffTransitionOn($assessment, $actor);
    }

    expect($queriesFor())->toBe($withOneStaffActor);
});

it('keeps the list and the form free of the status history', function (): void {
    $assessment = submittedByOwner();
    switchTo(ownerOf($assessment->entity));

    expect($this->getJson('/api/v1/assessments')->assertOk()->json('data.0'))->not->toHaveKey('status_history')
        ->and($this->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()->json('data.assessment'))->not->toHaveKey('status_history');
});
