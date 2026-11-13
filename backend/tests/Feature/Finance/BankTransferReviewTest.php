<?php

declare(strict_types=1);

use App\Data\Finance\TransferRejection;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\TransferRejectionReason;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Finance\BankTransferApproval;
use App\Services\Finance\BankTransferRejection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const BANK_TRANSFERS = '/api/v1/finance/bank-transfers';

const REVIEW_REASON = 'اعتماد الحوالة بعد مطابقة كشف الحساب البنكي.';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/**
 * @return array{owner: User, account: BankAccount, payment: Payment}
 */
function transferUnderReview(?string $contents = null): array
{
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();

    return ['owner' => $owner, 'account' => $account, 'payment' => submittedTransfer($owner, $account, $contents ?? SAMPLE_PDF.'%'.fake()->uuid())];
}

function anotherTransfer(BankAccount $account, ?string $contents = null): Payment
{
    return submittedTransfer(ownerOf(entityWithOwner()), $account, $contents ?? SAMPLE_PDF.'%'.fake()->uuid());
}

it('lists transfers awaiting approval oldest first, test ones included, with the counts', function (): void {
    ['account' => $account, 'payment' => $first] = transferUnderReview();
    $second = anotherTransfer($account);
    $rejected = anotherTransfer($account);
    rejectTransfer(transferReviewer(), $rejected)->assertOk();

    switchTo(transferReviewer())->getJson(BANK_TRANSFERS)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.counts.under_review', 2)
        ->assertJsonPath('data.0.id', $first->public_id)
        ->assertJsonPath('data.0.status', 'under_review')
        ->assertJsonPath('data.0.is_test', true)
        ->assertJsonPath('data.0.pricing.total', ACCREDITED_TOTAL)
        ->assertJsonPath('data.0.bank_account.iban', EXAMPLE_IBAN)
        ->assertJsonPath('data.0.proof.scan_status', 'clean')
        ->assertJsonPath('data.0.has_duplicate_proof', false)
        ->assertJsonPath('data.0.reviewed_by', null)
        ->assertJsonPath('data.1.id', $second->public_id);

    $this->getJson(BANK_TRANSFERS.'?sort=-submitted_at')->assertJsonPath('data.0.id', $second->public_id);
    $this->getJson(BANK_TRANSFERS.'?filter[status]=rejected')->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.rejection_reason', 'not_received');
    $this->getJson(BANK_TRANSFERS.'?filter[status]=all')->assertJsonPath('meta.pagination.total', 3);
    $this->getJson(BANK_TRANSFERS.'?filter[mode]=live')->assertJsonPath('meta.pagination.total', 0);
    $this->getJson(BANK_TRANSFERS.'?filter[bank_account]='.$account->public_id)->assertJsonPath('meta.pagination.total', 2);
    $this->getJson(BANK_TRANSFERS.'?filter[bank_account]='.fake()->uuid())->assertJsonPath('meta.pagination.total', 0);
    $this->getJson(BANK_TRANSFERS.'?search='.urlencode($second->reference))->assertJsonPath('meta.pagination.total', 1);
});

it('validates the queue filters', function (string $query): void {
    switchTo(transferReviewer())->getJson(BANK_TRANSFERS.'?'.$query)->assertUnprocessable();
})->with(['filter[status]=created', 'filter[colour]=red', 'sort=total', 'filter[bank_account]=SA03', 'per_page=500']);

it('flags a receipt file used for another request', function (): void {
    ['account' => $account, 'payment' => $first] = transferUnderReview(SAMPLE_PDF);
    $second = anotherTransfer($account, SAMPLE_PDF);

    switchTo(transferReviewer())->getJson(BANK_TRANSFERS)->assertJsonPath('data.0.has_duplicate_proof', true)->assertJsonPath('data.1.has_duplicate_proof', true);
    $this->getJson(bankTransferUrl($second))
        ->assertOk()
        ->assertJsonPath('data.has_duplicate_proof', true)
        ->assertJsonPath('data.duplicate_proofs', [['payment_id' => $first->public_id, 'reference' => $first->reference, 'status' => 'under_review']]);
});

it('hides transfers of deleted entities by default and refuses to decide them', function (): void {
    ['owner' => $owner, 'payment' => $payment] = transferUnderReview();
    $payment->entity->delete();
    $reviewer = transferReviewer();

    switchTo($reviewer)->getJson(BANK_TRANSFERS)->assertJsonPath('meta.pagination.total', 0)->assertJsonPath('meta.counts.under_review', 0);
    $this->getJson(BANK_TRANSFERS.'?filter[frozen]=1')->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.entity.is_frozen', true);

    approveTransfer($reviewer, $payment)->assertConflict()->assertJsonPath('code', 'entity.deleted');
    rejectTransfer($reviewer, $payment)->assertConflict()->assertJsonPath('code', 'entity.deleted');

    $payment->entity->restore();

    approveTransfer($reviewer, $payment)->assertOk();
});

it('shows a transfer with its proof and lets reviewers and operations viewers download the receipt', function (): void {
    ['payment' => $payment] = transferUnderReview();

    foreach ([transferReviewer(), systemAdmin()] as $staff) {
        switchTo($staff)->getJson(bankTransferUrl($payment))
            ->assertOk()
            ->assertJsonPath('data.reference', $payment->reference)
            ->assertJsonPath('data.duplicate_proofs', []);
        $url = (string) $this->postJson(bankTransferUrl($payment, 'proof/download'))->assertOk()->json('data.url');
        $this->get($url)->assertOk();
    }

    expect(Audit::query()->where('event', AuditEvent::FileDownloaded->value)->count())->toBe(2);
});

it('answers 404 for an unknown transfer or a card payment', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $card = paidPayment($owner, $gateway);
    switchTo(transferReviewer());

    $this->getJson(BANK_TRANSFERS.'/'.fake()->uuid())->assertNotFound();
    $this->getJson(bankTransferUrl($card))->assertNotFound();
    $this->postJson(bankTransferUrl($card, 'approve'))->assertNotFound();
});

it('keeps the queue away from users without finance access', function (string $kind): void {
    ['payment' => $payment] = transferUnderReview();
    $user = match ($kind) {
        'content officer' => contentOfficer(),
        'request follower' => requestFollower(),
        default => ownerOf(entityWithOwner()),
    };

    switchTo($user)->getJson(BANK_TRANSFERS)->assertForbidden();
    $this->getJson(bankTransferUrl($payment))->assertForbidden();
    $this->postJson(bankTransferUrl($payment, 'proof/download'))->assertForbidden();
})->with(['content officer', 'request follower', 'entity']);

it('refuses guests', function (): void {
    ['payment' => $payment] = transferUnderReview();
    app('auth')->forgetGuards();
    $this->flushSession();

    $this->getJson(BANK_TRANSFERS)->assertUnauthorized();
    $this->postJson(bankTransferUrl($payment, 'approve'))->assertUnauthorized();
});

it('approves a transfer: the payment is paid, a TEST receipt is issued, the request opens and everyone is told', function (): void {
    publishedAccreditedCriteria();
    ['owner' => $owner, 'payment' => $payment] = transferUnderReview();
    $reviewer = transferReviewer();
    $follower = requestFollower();

    approveTransfer($reviewer, $payment)
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.reviewed_by', ['id' => $reviewer->id, 'name' => $reviewer->name])
        ->assertJsonPath('data.receipt.number', null)
        ->assertJsonPath('data.receipt.is_test', true);

    $payment->refresh();
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->provider_payment_id)->toBeNull()
        ->and($receipt->snapshot['channel'] ?? null)->toBe('bank_transfer')
        ->and($receipt->snapshot['bank_name'] ?? null)->toBe(['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)'])
        ->and(Assessment::query()->where('payment_id', $payment->id)->where('path', AssessmentPath::Accredited)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::BankTransferApproved->value)->sole()->user_id)->toBe($reviewer->id)
        ->and(Audit::query()->where('event', AuditEvent::PaymentPaid->value)->sole()->old_values)->toBe(['status' => 'under_review'])
        ->and(financeNoticeSent($owner, NotificationCode::PaymentSucceeded))->toBeTrue()
        ->and(financeNoticeSent($owner, NotificationCode::PaymentReceiptIssued))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::PaymentSucceeded))->toBeTrue();
});

it('rejects a transfer with a preset reason and a note, closing the request for good', function (): void {
    ['owner' => $owner, 'payment' => $payment] = transferUnderReview();
    $reviewer = transferReviewer();

    rejectTransfer($reviewer, $payment, ['rejection_reason' => 'amount_mismatch', 'note' => TRANSFER_REVIEW_NOTE])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'amount_mismatch')
        ->assertJsonPath('data.review_note', TRANSFER_REVIEW_NOTE);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->rejected_at)->not->toBeNull()
        ->and(PaymentReceipt::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::BankTransferRejected->value)->sole()->new_values['rejection_reason'] ?? null)->toBe('amount_mismatch');

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::BankTransferRejected
        && str_contains($notification->notice->body->ar, __('finance.transfer_rejection_reasons.amount_mismatch', [], 'ar'))
        && str_contains($notification->notice->body->ar, TRANSFER_REVIEW_NOTE)
        && str_contains($notification->notice->body->en, 'Submit a new payment request'));

    approveTransfer($reviewer, $payment)->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    rejectTransfer($reviewer, $payment)->assertConflict();
});

it('validates the rejection', function (array $payload, string $field): void {
    ['payment' => $payment] = transferUnderReview();

    rejectTransfer(transferReviewer(), $payment, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($payment->refresh()->status)->toBe(PaymentStatus::UnderReview);
})->with([
    'missing reason' => [[], 'rejection_reason'],
    'unknown reason' => [['rejection_reason' => 'late'], 'rejection_reason'],
    'other without a note' => [['rejection_reason' => 'other'], 'note'],
    'note too short' => [['rejection_reason' => 'unreadable', 'note' => 'قصيرة'], 'note'],
    'note too long' => [['rejection_reason' => 'unreadable', 'note' => str_repeat('ملاحظة ', 80)], 'note'],
]);

it('lets only holders of the review permission decide, including users granted it individually', function (): void {
    ['payment' => $payment, 'account' => $account] = transferUnderReview();
    $other = anotherTransfer($account);
    $grantee = staffUser(RoleKey::ContentOfficer);

    approveTransfer(systemAdmin(), $payment)->assertForbidden();
    rejectTransfer(staffUser(RoleKey::RequestFollower), $payment)->assertForbidden();
    approveTransfer($grantee, $payment)->assertForbidden();

    $grantee->givePermissionTo(PermissionKey::FinanceTransfersReview->value);

    approveTransfer($grantee->refresh(), $payment)->assertOk();
    rejectTransfer($grantee, $other)->assertOk();
});

it('requires a reason from the Super Admin, hides the Super Admin name and alerts', function (): void {
    ['payment' => $payment] = transferUnderReview();
    $superAdmin = superAdmin();
    switchTo($superAdmin);

    $this->postJson(bankTransferUrl($payment, 'approve'))->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    expect($payment->refresh()->status)->toBe(PaymentStatus::UnderReview);

    $this->postJson(bankTransferUrl($payment, 'approve'), ['reason' => REVIEW_REASON])
        ->assertOk()
        ->assertJsonPath('data.reviewed_by', ['id' => $superAdmin->id, 'name' => null]);

    expect(Audit::query()->where('event', AuditEvent::BankTransferApproved->value)->sole()->reason)->toBe(REVIEW_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('decides each selected transfer on its own and reports what was skipped', function (): void {
    ['payment' => $first, 'account' => $account] = transferUnderReview();
    $second = anotherTransfer($account);
    $decided = anotherTransfer($account);
    $frozen = anotherTransfer($account);
    approveTransfer(transferReviewer(), $decided)->assertOk();
    $frozen->entity->delete();
    $unknown = fake()->uuid();

    switchTo(transferReviewer())->postJson(BANK_TRANSFERS.'/bulk-approve', ['payment_ids' => [$first->public_id, $second->public_id, $decided->public_id, $frozen->public_id, $unknown]])
        ->assertOk()
        ->assertJsonPath('data.decided', [$first->public_id, $second->public_id])
        ->assertJsonPath('data.skipped', [
            ['id' => $decided->public_id, 'code' => 'state.transition_illegal'],
            ['id' => $frozen->public_id, 'code' => 'entity.deleted'],
            ['id' => $unknown, 'code' => 'not_found'],
        ]);

    expect($first->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($second->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($frozen->refresh()->status)->toBe(PaymentStatus::UnderReview);
});

it('rejects several transfers with one reason and note', function (): void {
    ['payment' => $first, 'account' => $account] = transferUnderReview();
    $second = anotherTransfer($account);

    switchTo(transferReviewer())->postJson(BANK_TRANSFERS.'/bulk-reject', [
        'payment_ids' => [$first->public_id, $second->public_id],
        'rejection_reason' => 'other',
        'note' => TRANSFER_REVIEW_NOTE,
    ])->assertOk()->assertJsonPath('data.decided', [$first->public_id, $second->public_id])->assertJsonPath('data.skipped', []);

    foreach ([$first, $second] as $payment) {
        expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
            ->and($payment->bankTransfer?->review_note)->toBe(TRANSFER_REVIEW_NOTE);
    }
});

it('validates bulk decisions', function (string $endpoint, array $payload, string $field): void {
    Config::set('moashir.finance.bank_transfer.bulk_decision_max', 2);

    switchTo(transferReviewer())->postJson(BANK_TRANSFERS.'/'.$endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'empty selection' => ['bulk-approve', ['payment_ids' => []], 'payment_ids'],
    'too many' => ['bulk-approve', ['payment_ids' => [fake()->uuid(), fake()->uuid(), fake()->uuid()]], 'payment_ids'],
    'not a UUID' => ['bulk-approve', ['payment_ids' => ['PAY-2026-000001']], 'payment_ids.0'],
    'duplicate' => ['bulk-reject', ['payment_ids' => ['5b1c6f4e-3c35-4d1f-9a43-0f5a1f6b2a10', '5b1c6f4e-3c35-4d1f-9a43-0f5a1f6b2a10'], 'rejection_reason' => 'duplicate'], 'payment_ids.0'],
    'reject without a reason' => ['bulk-reject', ['payment_ids' => [fake()->uuid()]], 'rejection_reason'],
]);

it('keeps bulk decisions to reviewers', function (): void {
    ['payment' => $payment] = transferUnderReview();

    switchTo(systemAdmin())->postJson(BANK_TRANSFERS.'/bulk-approve', ['payment_ids' => [$payment->public_id]])->assertForbidden();
    $this->postJson(BANK_TRANSFERS.'/bulk-reject', ['payment_ids' => [$payment->public_id], 'rejection_reason' => 'duplicate'])->assertForbidden();
});

it('decides a transfer exactly once when two reviewers act on the same stale copy', function (): void {
    publishedAccreditedCriteria();
    ['payment' => $payment] = transferUnderReview();
    $first = Payment::query()->whereKey($payment->id)->sole();
    $second = Payment::query()->whereKey($payment->id)->sole();
    $reviewer = transferReviewer();

    app(BankTransferApproval::class)->approve($reviewer, $first);

    expect(fn () => app(BankTransferApproval::class)->approve(transferReviewer(), $second))->toThrow(IllegalStateTransition::class)
        ->and(fn () => app(BankTransferRejection::class)->reject(transferReviewer(), $second, new TransferRejection(TransferRejectionReason::Duplicate, null)))->toThrow(IllegalStateTransition::class)
        ->and(PaymentReceipt::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and(Assessment::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and($payment->refresh()->bankTransfer?->reviewed_by_user_id)->toBe($reviewer->id);
});

it('never lets a rejection overwrite an approval in the database', function (): void {
    ['payment' => $payment] = transferUnderReview();
    approveTransfer(transferReviewer(), $payment)->assertOk();

    expect(fn () => $payment->refresh()->bankTransfer?->forceFill(['rejection_reason' => TransferRejectionReason::Duplicate])->save())
        ->toThrow(QueryException::class);
});
