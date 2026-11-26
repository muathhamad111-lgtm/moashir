<?php

declare(strict_types=1);

use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use Illuminate\Testing\TestResponse;

const REVIEW_NOTE_BODY = 'يحتاج وصف السياسة إلى مطابقة الشواهد المرفوعة قبل اعتماد الدرجة.';

const REVIEW_NOTE_MARKER = 'N1-INTERNAL-MARKER';

function reviewNotesUrl(Assessment $assessment): string
{
    return "/api/v1/assessments/{$assessment->id}/review-notes";
}

/**
 * @return array<string, mixed>
 */
function mainReviewNote(CriteriaVersion $version, string $code, ?string $body = REVIEW_NOTE_BODY): array
{
    return ['level' => 'main', 'main_criterion_id' => mainOf($version, $code)->id, 'sub_criterion_id' => null, 'guiding_point_id' => null, 'body' => $body];
}

/**
 * @return array<string, mixed>
 */
function subReviewNote(CriteriaVersion $version, string $code, ?string $body = REVIEW_NOTE_BODY): array
{
    $sub = subOf($version, $code);

    return ['level' => 'sub', 'main_criterion_id' => $sub->main_criterion_id, 'sub_criterion_id' => $sub->id, 'guiding_point_id' => null, 'body' => $body];
}

/**
 * @return array<string, mixed>
 */
function pointReviewNote(CriteriaVersion $version, string $code, ?string $body = REVIEW_NOTE_BODY): array
{
    $point = pointOf($version, $code);
    $sub = SubCriterion::query()->findOrFail($point->sub_criterion_id);

    return ['level' => 'point', 'main_criterion_id' => $sub->main_criterion_id, 'sub_criterion_id' => $sub->id, 'guiding_point_id' => $point->id, 'body' => $body];
}

/**
 * @param  list<array<string, mixed>>  $notes
 */
function saveReviewNotes(User $author, Assessment $assessment, array $notes, ?string $reason = null): TestResponse
{
    return switchTo($author)->putJson(reviewNotesUrl($assessment), array_filter(['notes' => $notes, 'reason' => $reason], static fn (mixed $value): bool => $value !== null));
}

function entityVisibleContentOf(User $owner, Assessment $assessment): string
{
    $id = $assessment->id;
    $current = $assessment->isAdvanced() ? 'advanced/current' : 'accredited/current';
    $contents = [];

    foreach ([
        '/api/v1/assessments',
        "/api/v1/assessments/{$id}",
        "/api/v1/assessments/{$id}/form",
        "/api/v1/assessments/{$id}/result",
        "/api/v1/assessments/{$id}/evidence",
        "/api/v1/assessments/{$id}/feedback-report",
        "/api/v1/assessments/{$current}",
        "/api/v1/assessments/{$id}/review",
        "/api/v1/requests/{$id}",
        '/api/v1/notifications',
        '/api/v1/dashboard/summary',
        '/api/v1/dashboard/action-center',
    ] as $url) {
        $response = switchTo($owner)->getJson($url);
        expect($response->status())->toBeLessThan(500);
        $contents[] = (string) $response->getContent();
    }

    $bundle = switchTo($owner)->postJson("/api/v1/assessments/{$id}/evidence/bundles")->assertForbidden();
    $contents[] = (string) $bundle->getContent();

    return implode("\n", $contents);
}

/**
 * @return array{0: list<array<string, mixed>>, 1: string}
 */
function invalidReviewNotePayload(string $case, CriteriaVersion $version): array
{
    $policy = subReviewNote($version, 'M1.1');

    return match ($case) {
        'empty list' => [[], 'notes'],
        'body too long' => [[subReviewNote($version, 'M1.1', str_repeat('ن', 501))], 'notes.0.body'],
        'missing body' => [[array_diff_key($policy, ['body' => true])], 'notes.0.body'],
        'unknown level' => [[[...$policy, 'level' => 'criterion']], 'notes.0.level'],
        'main note with a sub' => [[[...mainReviewNote($version, 'M1'), 'sub_criterion_id' => $policy['sub_criterion_id']]], 'notes.0.sub_criterion_id'],
        'sub note without a sub' => [[[...$policy, 'sub_criterion_id' => null]], 'notes.0.sub_criterion_id'],
        'point note without a point' => [[[...pointReviewNote($version, 'M1.2.1'), 'guiding_point_id' => null]], 'notes.0.guiding_point_id'],
        'duplicate unit' => [[$policy, subReviewNote($version, 'M1.1', 'نص مختلف للوحدة نفسها.')], 'notes.1'],
        default => throw new LogicException('Unknown invalid review note case.'),
    };
}

function writeMarkerNotes(Assessment $assessment, CriteriaVersion $version, User $assessor, string $path): void
{
    saveReviewNotes($assessor, $assessment, [
        subReviewNote($version, 'M1.1', REVIEW_NOTE_MARKER."-{$path}-assessor-sub ملاحظة داخلية"),
        pointReviewNote($version, 'M1.2.1', REVIEW_NOTE_MARKER."-{$path}-assessor-point ملاحظة داخلية"),
    ])->assertOk();
    saveReviewNotes(requestFollower(), $assessment, [mainReviewNote($version, 'M1', REVIEW_NOTE_MARKER."-{$path}-follower-main ملاحظة داخلية")])->assertOk();
}

function assertNoNotesIn(string $content): void
{
    expect($content)->not->toContain(REVIEW_NOTE_MARKER)
        ->not->toContain('review_notes')
        ->not->toContain('can_write_review_notes')
        ->not->toContain('ملاحظة داخلية');
}
