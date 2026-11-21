<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const ASSESSOR_OVERVIEW = '/api/v1/dashboard/assessor-overview';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-17 08:00:00');
});

function assessorOverviewOf(User $assessor, string $query = ''): TestResponse
{
    return switchTo($assessor)->getJson(ASSESSOR_OVERVIEW.($query === '' ? '' : '?'.$query))->assertOk();
}

function returnedToEntity(Assessment $assessment, User $assessor): void
{
    $version = $assessment->criteriaVersion;

    scoreEveryUnit($assessor, $assessment);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى إرفاق محضر اعتماد مجلس الإدارة للسياسة وتحديث وصفها.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']],
        'deadline_at' => '2026-11-24T08:00:00+00:00',
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk()->assertJsonPath('data.status', 'needs_completion');
}

it('keeps the assessor overview to assessors', function (): void {
    $this->getJson(ASSESSOR_OVERVIEW)->assertUnauthorized();

    signIn(staffUser(RoleKey::FinanceOfficer))->getJson(ASSESSOR_OVERVIEW)->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson(ASSESSOR_OVERVIEW)->assertForbidden();

    assessorOverviewOf(assessorUser())->assertJsonPath('data.period.preset', 'month');
});

it('counts only the signed-in assessor\'s reviews, results and completion requests in the period', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $other = assessorUser();
    $completed = completedAccreditedRequest($version, $assessor);
    returnedToEntity(reviewedAccreditedRequest($version, $assessor), $assessor);
    completedAccreditedRequest($version, $other);

    $response = assessorOverviewOf($assessor);
    $cards = overviewCards($response);
    $percent = $completed->refresh()->currentResult?->percent->toDisplay();

    expect($cards->get('completed'))->toBe(['value' => 1, 'previous' => 0])
        ->and($cards->get('completion_requests'))->toBe(['value' => 1, 'previous' => 0])
        ->and($cards->get('average_result_percent'))->toBe(['value' => $percent, 'previous' => null])
        ->and($cards->get('average_duration_days')['previous'])->toBeNull()
        ->and($response->json('data.paths'))->toBe([['path' => 'accredited', 'completed' => 1], ['path' => 'advanced', 'completed' => 0]])
        ->and(array_sum($response->json('data.trends.series.completed')))->toBe(1)
        ->and(collect($response->json('data.levels'))->sum('count'))->toBe(1)
        ->and(collect($response->json('data.levels'))->pluck('key')->all())->toBe(['diamond', 'gold', 'silver', 'bronze', 'none']);

    expect(overviewCards(assessorOverviewOf($other))->get('completion_requests'))->toBe(['value' => 0, 'previous' => 0]);
});

it('moves completed reviews to the previous period when the window moves on', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    completedAccreditedRequest($version, $assessor);

    Date::setTestNow('2026-12-20 08:00:00');

    expect(overviewCards(assessorOverviewOf($assessor))->get('completed'))->toBe(['value' => 0, 'previous' => 1]);
});

it('refuses a malformed custom period', function (): void {
    switchTo(assessorUser())->getJson(ASSESSOR_OVERVIEW.'?period=custom&from=2026-11-10')->assertUnprocessable()->assertJsonValidationErrors(['to']);
});

it('shows the assessor board as live figures only: open by state, overdue and due within a week', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    reviewedAccreditedRequest($version, $assessor)->forceFill(['due_at' => '2026-11-20 08:00:00'])->saveQuietly();
    assignedAccreditedRequest($version, $assessor)->forceFill(['due_at' => '2026-11-10 08:00:00'])->saveQuietly();

    $board = boardOf($assessor, 'assessor');

    expect(boardCards($board)->all())->toBe(['to_start' => 1, 'in_progress' => 1, 'awaiting_entity' => 0, 'overdue' => 1, 'due_within_week' => 1])
        ->and($board->json('data.widgets'))->toBe([]);
});
