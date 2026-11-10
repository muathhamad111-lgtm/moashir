<?php

declare(strict_types=1);

use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('refuses to start, apply or pay on a paused or hidden path with one code', function (PathAvailability $availability): void {
    ['owner' => $owner] = payingEntity();
    $version = publishedAccreditedCriteria();
    setServicePrice(PaymentPurpose::SelfAssessment, '0.00', $availability);
    setServicePrice(PaymentPurpose::Accredited, '1000.00', $availability);
    setServicePrice(PaymentPurpose::Award, '0.00', $availability);
    $cycle = openAwardCycle($version);

    switchTo($owner)->postJson('/api/v1/assessments', ['path' => 'self'])->assertConflict()
        ->assertJsonPath('code', 'path.unavailable')->assertJsonPath('meta', ['path' => 'self', 'availability' => $availability->value]);
    switchTo($owner)->postJson('/api/v1/assessments/accredited')->assertConflict()->assertJsonPath('code', 'path.unavailable');
    startPayment($owner)->assertConflict()->assertJsonPath('code', 'path.unavailable')->assertJsonPath('meta.path', 'accredited');
    applyForCycle(ownerOf(cycleEntity($cycle)), $cycle)->assertConflict()->assertJsonPath('code', 'path.unavailable')->assertJsonPath('meta.path', 'award');

    expect(Assessment::query()->count())->toBe(0);
})->with([PathAvailability::Paused, PathAvailability::Hidden]);

it('keeps an existing draft working and the path listed when the path is hidden', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);
    $owner = ownerOf($draft->entity);
    setServicePrice(PaymentPurpose::SelfAssessment, '0.00', PathAvailability::Hidden);

    $self = boardWidget(boardOf($owner, 'entity'), 'paths')['self'];
    expect($self['pricing']['availability'])->toBe('hidden')
        ->and($self['is_listed'])->toBeTrue()
        ->and($self['open_draft']['id'])->toBe($draft->id);

    $newcomer = ownerOf(entityWithOwner());
    expect(boardWidget(boardOf($newcomer, 'entity'), 'paths')['self']['is_listed'])->toBeFalse()
        ->and(boardWidget(boardOf($newcomer, 'entity'), 'next_step')['key'] ?? null)->not->toBe('start_self');
});

it('reports a paused award path as not eligible with its reason', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    setServicePrice(PaymentPurpose::Award, '0.00', PathAvailability::Paused);

    expect(boardWidget(boardOf(ownerOf(cycleEntity($cycle)), 'entity'), 'paths')['award']['eligibility'])->toBe(['eligible' => false, 'reason' => 'path_unavailable']);
});
