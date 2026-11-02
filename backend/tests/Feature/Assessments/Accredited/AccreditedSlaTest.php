<?php

declare(strict_types=1);

use App\Actions\Assessments\RecomputeAccreditedDueDates;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Entities\FrozenDeadline;
use App\Notifications\Entities\EntityNotification;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('notifies the assessor, the followers and the System Administrators once when a review is overdue', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $follower = requestFollower();
    $admin = systemAdmin();

    $this->travel(15)->days();
    Artisan::call('moashir:assessments:flag-overdue');
    Artisan::call('moashir:assessments:flag-overdue');

    expect($assessment->refresh()->overdue_notified_at)->not->toBeNull()
        ->and(financeNoticeSent($assessor, NotificationCode::AccreditedReviewOverdue))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::AccreditedReviewOverdue))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::AccreditedReviewOverdue))->toBeTrue();

    Notification::assertSentToTimes($assessor, EntityNotification::class, 2);
});

it('does not flag a request waiting for the entity or of a deleted entity', function (): void {
    $version = publishedAccreditedCriteria();
    $waiting = reviewedAccreditedRequest($version, assessorUser());
    $waiting->forceFill(['status' => AccreditedAssessmentStatus::NeedsCompletion])->saveQuietly();
    $frozen = submittedAccreditedRequest($version);
    $frozen->entity->delete();

    $this->travel(20)->days();
    Artisan::call('moashir:assessments:flag-overdue');

    expect($waiting->refresh()->overdue_notified_at)->toBeNull()
        ->and($frozen->refresh()->overdue_notified_at)->toBeNull();
});

it('serves and updates the assessment settings for their permission holders', function (): void {
    switchTo(systemAdmin())->getJson('/api/v1/settings/assessment')->assertOk()
        ->assertJsonPath('data.accredited_review_sla_days', 14)
        ->assertJsonPath('data.upload_max_file_mb', 20)
        ->assertJsonPath('data.evidence_allowed_types', ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'])
        ->assertJsonPath('data.evidence_max_files_per_sub', 10);

    test()->putJson('/api/v1/settings/assessment', ['evidence_allowed_types' => ['pdf', 'png'], 'evidence_max_files_per_sub' => 5, 'upload_max_file_mb' => 15])->assertOk()
        ->assertJsonPath('data.evidence_allowed_types', ['pdf', 'png'])
        ->assertJsonPath('data.evidence_max_files_per_sub', 5)
        ->assertJsonPath('data.upload_max_file_mb', 15);

    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->count())->toBe(3);

    test()->putJson('/api/v1/settings/assessment', ['accredited_review_sla_days' => 0])->assertUnprocessable()->assertJsonValidationErrors(['accredited_review_sla_days']);
    test()->putJson('/api/v1/settings/assessment', ['evidence_allowed_types' => ['exe']])->assertUnprocessable()->assertJsonValidationErrors(['evidence_allowed_types.0']);
    test()->putJson('/api/v1/settings/assessment', ['evidence_allowed_types' => []])->assertUnprocessable();
    switchTo(requestFollower())->getJson('/api/v1/settings/assessment')->assertForbidden();
    switchTo(staffUser(RoleKey::FinanceOfficer))->putJson('/api/v1/settings/assessment', ['accredited_review_sla_days' => 10])->assertForbidden();
    switchTo(superAdmin())->putJson('/api/v1/settings/assessment', ['accredited_review_sla_days' => 10])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
});

it('shifts the due dates of open requests when the review SLA changes, once', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $open = reviewedAccreditedRequest($version, assessorUser());
    $done = completedAccreditedRequest($version, assessorUser());
    $doneDue = $done->due_at?->toIso8601String();

    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['accredited_review_sla_days' => 10])->assertOk()
        ->assertJsonPath('data.accredited_review_sla_days', 10);

    expect($open->refresh()->due_at?->toIso8601String())->toBe('2026-11-11T08:00:00+00:00')
        ->and($open->review_sla_days)->toBe(10)
        ->and($done->refresh()->due_at?->toIso8601String())->toBe($doneDue);

    app(RecomputeAccreditedDueDates::class)->handle();

    expect($open->refresh()->due_at?->toIso8601String())->toBe('2026-11-11T08:00:00+00:00')
        ->and(Audit::query()->where('event', AuditEvent::AssessmentDueDatesRecomputed->value)->count())->toBe(1);
});

it('shifts the paused due date of a frozen request with the SLA change', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    softDeleteWithOpenRequests($assessment->entity, [$assessment->request_number]);
    $paused = FrozenDeadline::query()->where('subject_id', $assessment->id)->where('attribute', 'due_at')->sole();

    app(SettingsStore::class)->put(SettingKey::AccreditedReviewSlaDays, 21, AuditEvent::AssessmentSettingsUpdated, null);
    app(RecomputeAccreditedDueDates::class)->handle();

    expect($paused->refresh()->remaining_seconds)->toBe(21 * 86400);

    Date::setTestNow('2026-11-05 08:00:00');
    restoreEntity($assessment->entity);

    expect($assessment->refresh()->due_at?->toIso8601String())->toBe('2026-11-26T08:00:00+00:00');
});
