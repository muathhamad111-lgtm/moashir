<?php

declare(strict_types=1);

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Services\Assessments\InitialReviewSla;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-11-05 08:00:00');
});

function slaAssessmentDueAt(?string $dueAt): Assessment
{
    return new Assessment()->forceFill(['due_at' => $dueAt, 'overdue_notified_at' => '2026-11-04 08:00:00']);
}

function slaReturnWithDays(?int $days): AssessmentCompletionRequest
{
    return new AssessmentCompletionRequest()->forceFill(['review_days_remaining' => $days]);
}

it('counts the review days left at a return, rounding a started day up and never below zero', function (?string $dueAt, ?int $days): void {
    expect(new InitialReviewSla()->remainingDaysOf(slaAssessmentDueAt($dueAt)))->toBe($days);
})->with([
    'whole days' => ['2026-11-15 08:00:00', 10],
    'a started day' => ['2026-11-15 09:00:00', 11],
    'past due' => ['2026-11-01 08:00:00', 0],
    'no clock' => [null, null],
]);

it('restarts the clock from the resume time with the remaining days, at least one', function (int $days, string $expected): void {
    $assessment = slaAssessmentDueAt('2026-11-01 08:00:00');

    new InitialReviewSla()->resumeAfter($assessment, slaReturnWithDays($days));

    expect($assessment->due_at?->toIso8601String())->toBe($expected)
        ->and($assessment->overdue_notified_at)->toBeNull();
})->with([
    'remaining days' => [6, '2026-11-11T08:00:00+00:00'],
    'nothing left' => [0, '2026-11-06T08:00:00+00:00'],
]);

it('leaves the clock alone for a return made before the assignment', function (): void {
    $assessment = slaAssessmentDueAt('2026-11-01 08:00:00');

    new InitialReviewSla()->resumeAfter($assessment, slaReturnWithDays(null));

    expect($assessment->due_at?->toIso8601String())->toBe('2026-11-01T08:00:00+00:00');
});
