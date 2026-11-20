<?php

declare(strict_types=1);

use App\Data\Mail\AccreditedProgress;
use App\Data\Mail\CertificateSeal;
use App\Data\Mail\MailBlocks;
use App\Data\Mail\RequestedItem;
use App\Data\Mail\RequestedItems;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\AccreditedJourneyStage;
use App\Enums\Notifications\JourneyStepState;
use App\Enums\Notifications\NotificationCode;
use App\Support\Mail\SampleMailBlocks;

it('marks the stages before the current one done and the later ones next', function (AccreditedJourneyStage $current, array $states): void {
    $progress = new AccreditedProgress($current);

    expect(array_map($progress->stateOf(...), $progress->stages()))->toBe($states);
})->with([
    'submitted' => [AccreditedJourneyStage::Submitted, [JourneyStepState::Current, JourneyStepState::Next, JourneyStepState::Next, JourneyStepState::Next]],
    'assignment' => [AccreditedJourneyStage::Assignment, [JourneyStepState::Done, JourneyStepState::Current, JourneyStepState::Next, JourneyStepState::Next]],
    'result' => [AccreditedJourneyStage::Result, [JourneyStepState::Done, JourneyStepState::Done, JourneyStepState::Done, JourneyStepState::Current]],
]);

it('labels only the done and current steps', function (): void {
    expect(JourneyStepState::Done->isLabelled())->toBeTrue()
        ->and(JourneyStepState::Current->isLabelled())->toBeTrue()
        ->and(JourneyStepState::Next->isLabelled())->toBeFalse()
        ->and(JourneyStepState::Done->isDone())->toBeTrue()
        ->and(JourneyStepState::Current->isDone())->toBeFalse();
});

it('adds each block without dropping the others', function (): void {
    $items = new RequestedItems([new RequestedItem('M1.1', null, CompletionItemKind::Cv, null)]);
    $blocks = new MailBlocks()
        ->withItems($items)
        ->withProgress(new AccreditedProgress(AccreditedJourneyStage::Review))
        ->withSeal(new CertificateSeal(CertificateLevel::Diamond));

    expect($blocks->items)->toBe($items)
        ->and($blocks->progress?->current)->toBe(AccreditedJourneyStage::Review)
        ->and($blocks->seal?->level)->toBe(CertificateLevel::Diamond)
        ->and($blocks->listedItems())->toBe($items);
});

it('lists no items when there are none', function (?RequestedItems $items): void {
    expect(new MailBlocks($items)->listedItems())->toBeNull();
})->with([
    'no list' => [null],
    'an empty list' => [new RequestedItems([])],
]);

it('gives the preview steps to ACC-03 and a gold seal to ACC-09, for the entity only', function (): void {
    $submitted = SampleMailBlocks::for(NotificationCode::AccreditedSubmitted, 'notifications.assessments.accredited.ACC-03.entity', Locale::Arabic);
    $issued = SampleMailBlocks::for(NotificationCode::AccreditedCertificateIssued, 'notifications.assessments.accredited.ACC-09.entity', Locale::Arabic);
    $staff = SampleMailBlocks::for(NotificationCode::AccreditedCertificateIssued, 'notifications.assessments.accredited.ACC-09.staff', Locale::Arabic);

    expect($submitted->progress?->current)->toBe(AccreditedJourneyStage::Assignment)
        ->and($submitted->seal)->toBeNull()
        ->and($issued->seal?->level)->toBe(CertificateLevel::Gold)
        ->and($staff->seal)->toBeNull()
        ->and($staff->progress)->toBeNull();
});
