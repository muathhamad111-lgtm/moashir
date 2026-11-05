<?php

declare(strict_types=1);

use App\Data\Assessments\AttachmentSlot;
use App\Data\Assessments\BoundSub;
use App\Data\Assessments\RemainingSub;
use App\Data\Assessments\SubmissionCoverage;
use App\Data\Assessments\SubmissionProgress;
use App\Support\ValueObjects\LocalizedText;

function coveredSub(string $code, bool $hasCv, AttachmentSlot ...$slots): BoundSub
{
    return new BoundSub(10, $code, new LocalizedText('سياسة الحوكمة المعتمدة', 'Approved governance policy'), $hasCv, array_values($slots));
}

it('applies the submission rules to one sub-criterion', function (): void {
    $mixed = coveredSub('1.3', true, new AttachmentSlot('policy_document', true), new AttachmentSlot('board_minutes', false));
    $coverage = new SubmissionCoverage(['1.3' => '   '], ['1.3|board_minutes' => true]);

    expect($coverage->lacksCv($mixed))->toBeTrue()
        ->and($coverage->missingSlotKeysOf($mixed))->toBe(['policy_document'])
        ->and($coverage->covers($mixed))->toBeFalse()
        ->and(RemainingSub::of($mixed, $coverage)->toArray())->toBe([
            'sub_criterion_id' => 10,
            'code' => '1.3',
            'name' => ['ar' => 'سياسة الحوكمة المعتمدة', 'en' => 'Approved governance policy'],
            'cv_missing' => true,
            'attachments_required' => 1,
            'attachments_uploaded' => 0,
        ]);
});

it('treats a sub-criterion without CV and without required slots as complete', function (): void {
    $plain = coveredSub('2.1', false, new AttachmentSlot('board_minutes', false));

    expect(new SubmissionCoverage([], [])->covers($plain))->toBeTrue();
});

it('completes a CV-only sub-criterion with its CV text and a slot with its evidence', function (): void {
    $cvOnly = coveredSub('1.2', true);
    $evidenceOnly = coveredSub('1.4', false, new AttachmentSlot('policy_document', true));
    $coverage = new SubmissionCoverage(['1.2' => ACCREDITED_CV_TEXT], ['1.4|policy_document' => true]);

    expect($coverage->covers($cvOnly))->toBeTrue()
        ->and($coverage->covers($evidenceOnly))->toBeTrue();
});

it('counts the remaining sub-criteria of a progress', function (): void {
    $remaining = RemainingSub::of(coveredSub('1.3', true), new SubmissionCoverage([], []));
    $progress = new SubmissionProgress(16, 15, [$remaining]);

    expect($progress->remainingCount())->toBe(1)
        ->and($progress->toArray()['subs_total'])->toBe(16)
        ->and($progress->toArray()['remaining'])->toHaveCount(1);
});
