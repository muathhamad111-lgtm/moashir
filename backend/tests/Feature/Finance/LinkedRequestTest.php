<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    publishedAccreditedCriteria();
});

/**
 * @return array{owner: User, account: BankAccount, payment: Payment}
 */
function linkedTransferUnderReview(): array
{
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();

    return ['owner' => $owner, 'account' => $account, 'payment' => submittedTransfer($owner, $account, SAMPLE_PDF.'%'.fake()->uuid())];
}

it('has no linked request while the transfer is under review', function (): void {
    ['payment' => $payment] = linkedTransferUnderReview();

    switchTo(transferReviewer())->getJson(bankTransferUrl($payment))->assertOk()->assertJsonPath('data.linked_request', null);
    switchTo(financeOfficer())->getJson('/api/v1/finance/transactions/'.$payment->public_id)->assertOk()->assertJsonPath('data.linked_request', null);
});

it('shows Finance the number, status and progress of the opened request without a link to it', function (): void {
    ['payment' => $payment] = linkedTransferUnderReview();
    $reviewer = transferReviewer();

    $approved = approveTransfer($reviewer, $payment)->assertOk();
    $assessment = Assessment::query()->where('payment_id', $payment->id)->sole();

    expect($approved->json('data.linked_request'))->toBe([
        'number' => $assessment->request_number,
        'path' => 'accredited',
        'path_label' => $approved->json('data.linked_request.path_label'),
        'status' => 'draft',
        'progress' => ['subs_complete' => 1, 'subs_total' => 3],
    ]);

    $review = switchTo($reviewer)->getJson(bankTransferUrl($payment))->assertOk()->assertJsonPath('data.linked_request.number', $assessment->request_number);
    $transaction = test()->getJson('/api/v1/finance/transactions/'.$payment->public_id)->assertOk()->assertJsonPath('data.linked_request.status', 'draft');

    expect($review->json('data.linked_request'))->not->toHaveKey('id')
        ->and($transaction->json('data.linked_request'))->not->toHaveKey('id');

    test()->getJson("/api/v1/requests/{$assessment->id}")->assertForbidden();
    test()->getJson("/api/v1/assessments/{$assessment->id}")->assertForbidden();
});

it('links the request for the holders of the request queue', function (): void {
    ['payment' => $payment] = linkedTransferUnderReview();
    approveTransfer(transferReviewer(), $payment)->assertOk();
    $assessment = Assessment::query()->where('payment_id', $payment->id)->sole();

    switchTo(systemAdmin())->getJson(bankTransferUrl($payment))->assertOk()->assertJsonPath('data.linked_request.id', $assessment->id);
    test()->getJson('/api/v1/finance/transactions/'.$payment->public_id)->assertOk()->assertJsonPath('data.linked_request.id', $assessment->id);
    test()->getJson("/api/v1/requests/{$assessment->id}")->assertOk();
});

it('keeps the transfer review away from the requests followers', function (): void {
    ['payment' => $payment] = linkedTransferUnderReview();

    switchTo(staffUser(RoleKey::RequestFollower))->getJson(bankTransferUrl($payment))->assertForbidden();
});
