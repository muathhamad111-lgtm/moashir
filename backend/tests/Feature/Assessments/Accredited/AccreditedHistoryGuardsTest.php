<?php

declare(strict_types=1);

use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Finance\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('keeps the assignment history: only an active assignment may end, nothing is deleted', function (): void {
    $version = publishedAccreditedCriteria();
    $first = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $first);
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => assessorUser()->id, 'reason' => ASSIGNMENT_REASON])->assertOk();
    $ended = AssessmentAssignment::query()->where('assessor_user_id', $first->id)->sole();
    $active = AssessmentAssignment::query()->where('assessment_id', $assessment->id)->active()->sole();

    expect(fn () => DB::transaction(fn () => DB::table('assessment_assignments')->where('id', $ended->id)->update(['status' => 'active', 'ended_at' => null, 'end_reason' => null])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_assignments')->where('id', $active->id)->update(['assessor_user_id' => $first->id])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_assignments')->where('id', $active->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => $ended->forceFill(['end_reason' => 'تعديل'])->save())
        ->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $active->delete())
        ->toThrow(AppendOnlyViolation::class);
});

it('keeps the feedback versions: a row is only superseded once', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = scoredAccreditedReview($version, assessorUser());
    $item = AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->current()->firstOrFail();

    expect(fn () => DB::transaction(fn () => DB::table('assessment_feedback_items')->where('id', $item->id)->update(['body' => 'نص معدل'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_feedback_items')->where('id', $item->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => $item->forceFill(['body' => 'نص معدل'])->save())
        ->toThrow(AppendOnlyViolation::class);
});

it('enforces one open accredited request per entity in the database and allows a free request without a payment', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $payment = Payment::query()->findOrFail($assessment->payment_id);

    expect(fn () => DB::transaction(fn () => DB::table('assessments')->insert([
        'request_number' => 'ACC-2026-999999',
        'entity_id' => $assessment->entity_id,
        'path' => 'accredited',
        'status' => 'draft',
        'criteria_version_id' => $assessment->criteria_version_id,
        'scale_kind' => 'of_10',
        'payment_id' => storedPaidPayment($assessment->entity)->id,
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'assessments_one_open_accredited')
        ->and($payment->id)->toBe($assessment->payment_id)
        ->and(DB::table('assessments')->where('id', $assessment->id)->update(['payment_id' => null]))->toBe(1);
});
