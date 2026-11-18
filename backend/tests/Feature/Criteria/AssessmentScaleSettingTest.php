<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Notifications\Criteria\CriteriaNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    config()->set('moashir.assessment.scale_kind', 'of_10');
});

it('shows the chosen scale and the scale of the published version in the assessment settings', function (): void {
    publishedSelfCriteria();

    switchTo(systemAdmin())->getJson('/api/v1/settings/assessment')->assertOk()
        ->assertJsonPath('data.scale_kind', 'of_10')
        ->assertJsonPath('data.published_version.scale_kind', 'of_10');
});

it('stamps the scale chosen in the settings on the next published version only', function (): void {
    $published = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $administrator = systemAdmin();

    switchTo($administrator)->putJson('/api/v1/settings/assessment', ['scale_kind' => 'relative'])->assertOk()
        ->assertJsonPath('data.scale_kind', 'relative')
        ->assertJsonPath('data.published_version.scale_kind', 'of_10');

    expect($published->refresh()->scale_kind->value)->toBe('of_10')
        ->and($draft->refresh()->scale_kind->value)->toBe('of_10')
        ->and(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->count())->toBe(1);

    $next = republishWithNewSubWeights('new_only');

    expect($next->scale_kind->value)->toBe('relative')
        ->and($draft->refresh()->scale_kind->value)->toBe('of_10');
    Notification::assertSentTo($administrator, CriteriaNotification::class, static fn (CriteriaNotification $notification): bool => $notification->notice->code === NotificationCode::CriteriaScaleChanged);
});

it('validates the scale and lets only assessment-settings managers change it', function (): void {
    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['scale_kind' => 'out_of_5'])->assertUnprocessable()
        ->assertJsonValidationErrors('scale_kind');
    switchTo(criteriaOfficer())->putJson('/api/v1/settings/assessment', ['scale_kind' => 'relative'])->assertForbidden();
});
