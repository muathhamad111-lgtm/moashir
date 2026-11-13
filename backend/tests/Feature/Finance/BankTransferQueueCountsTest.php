<?php

declare(strict_types=1);

use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const TRANSFER_QUEUE = '/api/v1/finance/bank-transfers';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 06:00:00', 'UTC'));
});

function transferAwaitingDecision(BankAccount $account): Payment
{
    return submittedTransfer(ownerOf(entityWithOwner()), $account, SAMPLE_PDF.'%'.fake()->uuid());
}

/**
 * @return array{under_review: int, approved_today: int, rejected_today: int}
 */
function queueCounts(int $underReview, int $approvedToday, int $rejectedToday): array
{
    return ['under_review' => $underReview, 'approved_today' => $approvedToday, 'rejected_today' => $rejectedToday];
}

it('counts the approvals and rejections decided on the current Riyadh calendar day', function (): void {
    ['account' => $account] = transferPayingEntity();
    $decidedYesterday = transferAwaitingDecision($account);
    $approved = transferAwaitingDecision($account);
    $rejected = transferAwaitingDecision($account);
    transferAwaitingDecision($account);
    $reviewer = transferReviewer();

    $this->travelTo(CarbonImmutable::parse('2026-11-05 20:59:59', 'UTC'));
    rejectTransfer($reviewer, $decidedYesterday)->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-11-05 21:00:00', 'UTC'));
    approveTransfer($reviewer, $approved)->assertOk();
    rejectTransfer($reviewer, $rejected, ['rejection_reason' => 'other', 'note' => TRANSFER_REVIEW_NOTE])->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-11-06 20:59:59', 'UTC'));
    switchTo($reviewer)->getJson(TRANSFER_QUEUE)->assertOk()->assertJsonPath('meta.counts', queueCounts(1, 1, 1));
    $this->getJson(TRANSFER_QUEUE.'?filter[status]=paid&filter[mode]=live&filter[purpose]=bag')
        ->assertJsonPath('meta.pagination.total', 0)
        ->assertJsonPath('meta.counts', queueCounts(1, 1, 1));

    expect($approved->refresh()->isTest())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-11-06 21:00:00', 'UTC'));
    switchTo($reviewer)->getJson(TRANSFER_QUEUE)->assertJsonPath('meta.counts', queueCounts(1, 0, 0));
});

it('starts the day with zero decisions', function (): void {
    ['account' => $account] = transferPayingEntity();
    transferAwaitingDecision($account);

    switchTo(systemAdmin())->getJson(TRANSFER_QUEUE)->assertOk()->assertJsonPath('meta.counts', queueCounts(1, 0, 0));
});

it('leaves the decisions on deleted entities out of the counts, like the waiting count', function (): void {
    ['account' => $account] = transferPayingEntity();
    $approved = transferAwaitingDecision($account);
    $rejected = transferAwaitingDecision($account);
    $waiting = transferAwaitingDecision($account);
    $reviewer = transferReviewer();
    approveTransfer($reviewer, $approved)->assertOk();
    rejectTransfer($reviewer, $rejected)->assertOk();

    switchTo($reviewer)->getJson(TRANSFER_QUEUE)->assertJsonPath('meta.counts', queueCounts(1, 1, 1));

    foreach ([$approved, $rejected, $waiting] as $payment) {
        $payment->entity->delete();
    }

    $this->getJson(TRANSFER_QUEUE)->assertJsonPath('meta.counts', queueCounts(0, 0, 0));
    $this->getJson(TRANSFER_QUEUE.'?filter[frozen]=1&filter[status]=all')
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.counts', queueCounts(0, 0, 0));
});
