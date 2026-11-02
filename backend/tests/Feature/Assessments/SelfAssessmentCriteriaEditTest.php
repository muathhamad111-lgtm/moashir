<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Listeners\Assessments\RebindOpenAssessmentsAfterCriteriaEdit;
use App\Models\Assessments\AssessmentScore;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaEditEvent;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Notification::fake();
});

it('re-binds open drafts under scope "all" and keeps the scores of unchanged items', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);

    $next = republishWithNewSubWeights('all');

    $draft->refresh();
    $edit = CriteriaEditEvent::query()->sole();
    $audit = Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->sole();

    expect($draft->criteria_version_id)->toBe($next->id)
        ->and($edit->affected_open_count)->toBe(1)
        ->and(currentScoreCodes($draft, $next))->toBe(['M1.2.1', 'M1.2.2', 'M2.1'])
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->current()->where('guiding_point_id', pointOf($next, 'M1.2.1')->id)->sole()->raw_value)->toBe('10.00')
        ->and($audit->new_values)->toMatchArray(['criteria_version_id' => $next->id, 'scope' => 'all', 'cleared_count' => 1])
        ->and($audit->new_values['kept_codes'])->toBe(['M1.2.1', 'M1.2.2', 'M2.1']);

    Notification::assertNotSentTo(ownerOf($draft->entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::CriteriaEditedWithScope);

    switchTo(ownerOf($draft->entity))->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria.version_id', $next->id)
        ->assertJsonPath('data.progress', ['scored' => 3, 'required' => 4]);
});

it('re-binds open drafts under scope "reset_open", clears every score and informs the entity', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);
    $owner = ownerOf($draft->entity);

    $next = republishWithNewSubWeights('reset_open');

    expect($draft->refresh()->criteria_version_id)->toBe($next->id)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->current()->count())->toBe(0)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->count())->toBe(4);

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::CriteriaEditedWithScope
        && in_array('mail', $channels, true)
        && str_contains($notification->notice->body->en, $draft->request_number));
});

it('leaves open drafts on their version under scope "new_only"', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);

    republishWithNewSubWeights('new_only');

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->current()->count())->toBe(4)
        ->and(CriteriaEditEvent::query()->sole()->affected_open_count)->toBe(0);

    switchTo(ownerOf($draft->entity))->postJson("/api/v1/assessments/{$draft->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.criteria_version_id', $version->id)
        ->assertJsonPath('data.percent', '76.67');
});

it('never re-binds a closed self result', function (): void {
    $version = publishedSelfCriteria();
    $closed = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->postJson("/api/v1/assessments/{$closed->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();

    republishWithNewSubWeights('all');

    expect($closed->refresh()->criteria_version_id)->toBe($version->id)
        ->and(CriteriaEditEvent::query()->sole()->affected_open_count)->toBe(0);

    switchTo(ownerOf($closed->entity))->getJson("/api/v1/assessments/{$closed->id}/result")
        ->assertOk()
        ->assertJsonPath('data.percent', '76.67')
        ->assertJsonPath('data.criteria_version_id', $version->id);
});

it('leaves the drafts of deleted entities frozen during a criteria edit', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    switchTo(systemAdmin())->deleteJson("/api/v1/entities/{$entity->id}", [
        'reason' => 'طلبت الجهة إغلاق حسابها بخطاب رسمي من مجلس الإدارة.',
        'confirm_open_requests' => true,
        'acknowledged_request_numbers' => [$draft->request_number],
    ])->assertNoContent();

    republishWithNewSubWeights('reset_open');

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->current()->count())->toBe(4)
        ->and(CriteriaEditEvent::query()->sole()->affected_open_count)->toBe(0);
});

it('shows text corrections on closed results without re-binding anything', function (): void {
    $version = publishedSelfCriteria();
    $closed = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->postJson("/api/v1/assessments/{$closed->id}/submit", ['scores' => selfScores($version, SELF_FULL_MARKS_OF_10)])->assertOk();
    $draft = scoredSelfDraft($version);
    $corrected = ['ar' => 'القيادة والحوكمة المؤسسية', 'en' => 'Institutional Leadership and Governance'];

    switchTo(criteriaOfficer())->patchJson("/api/v1/criteria/versions/{$version->id}/labels", [
        'nodes' => [['type' => 'main', 'id' => mainOf($version, 'M1')->id, 'name' => $corrected]],
    ])->assertOk();

    expect($draft->refresh()->criteria_version_id)->toBe($version->id)
        ->and(AssessmentScore::query()->where('assessment_id', $draft->id)->current()->count())->toBe(4)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentRebound->value)->count())->toBe(0);

    switchTo(ownerOf($closed->entity))->getJson("/api/v1/assessments/{$closed->id}/result")
        ->assertOk()
        ->assertJsonPath('data.main.0.name', $corrected)
        ->assertJsonPath('data.percent', '76.67');
});

it('re-binds through the queue after a serialization round trip of the event', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);
    Queue::fake();

    $next = republishWithNewSubWeights('all');

    expect($draft->refresh()->criteria_version_id)->toBe($version->id);

    Queue::assertPushed(CallQueuedListener::class, static fn (CallQueuedListener $job): bool => $job->class === RebindOpenAssessmentsAfterCriteriaEdit::class);
    $job = Queue::pushed(CallQueuedListener::class, static fn (CallQueuedListener $job): bool => $job->class === RebindOpenAssessmentsAfterCriteriaEdit::class)->sole();
    $restored = unserialize(serialize($job));
    $restored->setJob(new FakeJob);
    $restored->handle(app());

    expect($draft->refresh()->criteria_version_id)->toBe($next->id)
        ->and(currentScoreCodes($draft, $next))->toBe(['M1.2.1', 'M1.2.2', 'M2.1']);
});
