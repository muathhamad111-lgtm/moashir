<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function refundEndpoint(Payment $payment): string
{
    return "/api/v1/finance/payments/{$payment->public_id}/refunds";
}

/**
 * @return array{payment: Payment, owner: User}
 */
function refundablePayment(): array
{
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();

    return ['payment' => paidPayment($owner, $gateway), 'owner' => $owner];
}

it('records a full refund with its scanned bank-transfer receipt and voids the payment receipt', function (): void {
    ['payment' => $payment, 'owner' => $owner] = refundablePayment();
    $finance = financeOfficer();
    $admin = systemAdmin();

    $response = switchTo($finance)->post(refundEndpoint($payment), refundPayload($payment), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'recorded')
        ->assertJsonPath('data.amount', ACCREDITED_TOTAL)
        ->assertJsonPath('data.transfer_reference', 'TRF-2026-55871')
        ->assertJsonPath('data.reason', REFUND_REASON)
        ->assertJsonPath('data.recorded_by', ['id' => $finance->id, 'name' => $finance->name])
        ->assertJsonPath('data.payment.id', $payment->public_id)
        ->assertJsonPath('data.evidence.name', 'bank-transfer.pdf')
        ->assertJsonPath('data.evidence.mime_type', 'application/pdf')
        ->assertJsonPath('data.evidence.scan_status', 'clean');

    $refund = Refund::query()->sole();
    $evidence = StoredFile::query()->whereKey($refund->evidence_file_id)->sole();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_at)->not->toBeNull()
        ->and($payment->receipt?->status)->toBe(ReceiptStatus::Voided)
        ->and($payment->receipt?->voided_by_refund_id)->toBe($refund->id)
        ->and($payment->receipt?->pdf_file_id)->toBeNull()
        ->and($evidence->purpose)->toBe(StoredFilePurpose::RefundEvidence)
        ->and($evidence->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($evidence->attachable_type)->toBe($refund->getMorphClass())
        ->and($evidence->attachable_id)->toBe($refund->id)
        ->and($evidence->path)->toStartWith('files/refund-evidence/')
        ->and($evidence->path)->not->toContain('bank-transfer')
        ->and(Storage::disk('local')->exists($evidence->path))->toBeTrue()
        ->and($response->json('data.id'))->toBe($refund->public_id)
        ->and(PaymentEvent::query()->where('event_type', 'refund_recorded')->count())->toBe(1);

    foreach ([AuditEvent::RefundRecorded, AuditEvent::PaymentRefunded, AuditEvent::ReceiptVoided, AuditEvent::FileUploaded, AuditEvent::FileScanned] as $event) {
        expect(Audit::query()->where('event', $event->value)->count())->toBe(1);
    }

    expect(Audit::query()->where('event', AuditEvent::RefundRecorded->value)->value('reason'))->toBe(REFUND_REASON)
        ->and(financeNoticeSent($owner, NotificationCode::PaymentRefundRecorded))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::PaymentRefundRecorded))->toBeTrue();
    Notification::assertNotSentTo($finance, EntityNotification::class, static fn ($notification): bool => $notification->notice->code === NotificationCode::PaymentRefundRecorded);
});

it('lets the System Administrator and the Super Admin record refunds', function (string $recorder): void {
    ['payment' => $payment] = refundablePayment();

    switchTo($recorder === 'super_admin' ? superAdmin() : systemAdmin())->post(refundEndpoint($payment), refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();
})->with(['system_admin', 'super_admin']);

it('refuses refunds from everyone else', function (): void {
    ['payment' => $payment, 'owner' => $owner] = refundablePayment();

    foreach ([$owner, staffUser(RoleKey::RequestFollower), staffUser(RoleKey::Assessor), staffUser(RoleKey::ContentOfficer)] as $user) {
        switchTo($user)->post(refundEndpoint($payment), refundPayload($payment), ['Accept' => 'application/json'])->assertForbidden();
    }

    expect(Refund::query()->count())->toBe(0)->and(StoredFile::query()->ofPurpose(StoredFilePurpose::RefundEvidence)->count())->toBe(0);
});

it('accepts only the full payment total', function (): void {
    ['payment' => $payment] = refundablePayment();

    switchTo(financeOfficer())->post(refundEndpoint($payment), refundPayload($payment, ['amount' => '575.00']), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'refund.amount_must_equal_total')
        ->assertJsonPath('meta.payment_total', ACCREDITED_TOTAL);

    expect(Refund::query()->count())->toBe(0)->and(StoredFile::withTrashed()->ofPurpose(StoredFilePurpose::RefundEvidence)->count())->toBe(0);
});

function evidenceUpload(string $kind): UploadedFile
{
    return match ($kind) {
        'infected' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n".FakeFileScanner::TEST_SIGNATURE."\n%%EOF"),
        'renamed_image' => UploadedFile::fake()->createWithContent('receipt.pdf', "\x89PNG\r\n\x1a\n0000IHDR"),
        'text' => UploadedFile::fake()->createWithContent('receipt.txt', 'transfer done'),
        default => UploadedFile::fake()->create('receipt.pdf', 21 * 1024, 'application/pdf'),
    };
}

it('needs a clean bank-transfer receipt file', function (string $kind, string $code): void {
    ['payment' => $payment] = refundablePayment();

    switchTo(financeOfficer())->post(refundEndpoint($payment), refundPayload($payment, ['evidence' => evidenceUpload($kind)]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', $code);

    expect(Refund::query()->count())->toBe(0)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::RefundEvidence)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('files/refund-evidence'))->toBe([]);
})->with([
    'infected file' => ['infected', 'file.infected'],
    'image renamed as pdf' => ['renamed_image', 'file.content_mismatch'],
    'text file' => ['text', 'validation_failed'],
    'larger than the limit' => ['oversized', 'validation_failed'],
]);

it('reports a failed scan when the scanner is unavailable', function (): void {
    ['payment' => $payment] = refundablePayment();
    app(FakeFileScanner::class)->makeUnavailable();

    switchTo(financeOfficer())->post(refundEndpoint($payment), refundPayload($payment), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.scan_failed');

    expect(Refund::query()->count())->toBe(0);
});

it('validates the refund details', function (array $overrides, string $field): void {
    ['payment' => $payment] = refundablePayment();

    switchTo(financeOfficer())->post(refundEndpoint($payment), refundPayload($payment, $overrides), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing evidence' => [['evidence' => null], 'evidence'],
    'short reason' => [['reason' => 'مسترد'], 'reason'],
    'future date' => [['refunded_on' => now('Asia/Riyadh')->addDay()->toDateString()], 'refunded_on'],
    'not a date' => [['refunded_on' => '01/11/2026'], 'refunded_on'],
    'amount format' => [['amount' => '1150'], 'amount'],
    'short transfer reference' => [['transfer_reference' => 'TR'], 'transfer_reference'],
]);

it('refuses a refund dated before the payment', function (): void {
    ['payment' => $payment] = refundablePayment();

    switchTo(financeOfficer())->post(refundEndpoint($payment), refundPayload($payment, ['refunded_on' => '2026-01-01']), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'refund.date_before_payment');
});

it('refuses payments that are not paid and a second refund', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $open = openedPayment($owner);
    $finance = financeOfficer();

    switchTo($finance)->post(refundEndpoint($open), refundPayload($open, ['amount' => $open->total, 'refunded_on' => now('Asia/Riyadh')->toDateString()]), ['Accept' => 'application/json'])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal');

    $paid = paidPayment($owner, $gateway, 'bag');
    switchTo($finance)->post(refundEndpoint($paid), refundPayload($paid), ['Accept' => 'application/json'])->assertCreated();
    switchTo($finance)->post(refundEndpoint($paid->refresh()), refundPayload($paid), ['Accept' => 'application/json'])->assertConflict();

    expect(Refund::query()->count())->toBe(1);
});

it('lists and shows recorded refunds to finance and lets them download the evidence through a short signed link', function (): void {
    ['payment' => $payment] = refundablePayment();
    $finance = financeOfficer();
    switchTo($finance)->post(refundEndpoint($payment), refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();
    $refund = Refund::query()->sole();

    switchTo($finance)->getJson('/api/v1/finance/refunds')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $refund->public_id);
    $this->getJson('/api/v1/finance/refunds?filter[purpose]=bag')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson("/api/v1/finance/refunds/{$refund->public_id}")->assertOk()->assertJsonPath('data.transfer_reference', 'TRF-2026-55871');

    $link = $this->postJson("/api/v1/finance/refunds/{$refund->public_id}/evidence/download")->assertOk()
        ->assertJsonPath('data.file_name', 'bank-transfer.pdf');
    $url = (string) $link->json('data.url');

    expect($url)->toContain('signature=')->and($url)->toContain('expires=');

    $download = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($download->streamedContent())->toStartWith('%PDF-')
        ->and(Audit::query()->where('event', AuditEvent::FileDownloaded->value)->count())->toBe(1);

    switchTo(systemAdmin())->get($url)->assertForbidden()->assertJsonPath('code', 'signature.invalid');
    switchTo($finance)->get($url.'x')->assertForbidden()->assertJsonPath('code', 'signature.invalid');
    $this->travel(6)->minutes();
    switchTo($finance)->get($url)->assertStatus(410)->assertJsonPath('code', 'signature.expired');
});

it('keeps refund records and evidence away from entities and other staff', function (): void {
    ['payment' => $payment, 'owner' => $owner] = refundablePayment();
    switchTo(financeOfficer())->post(refundEndpoint($payment), refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();
    $refund = Refund::query()->sole();

    foreach ([$owner, staffUser(RoleKey::Assessor)] as $user) {
        switchTo($user)->getJson('/api/v1/finance/refunds')->assertForbidden();
        $this->getJson("/api/v1/finance/refunds/{$refund->public_id}")->assertForbidden();
        $this->postJson("/api/v1/finance/refunds/{$refund->public_id}/evidence/download")->assertForbidden();
    }
});
