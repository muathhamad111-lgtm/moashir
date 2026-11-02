<?php

declare(strict_types=1);

use App\Contracts\Documents\PdfRenderer;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Finance\PaymentReceipt;
use App\Services\Finance\ReceiptDocumentBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('shows the own receipt and renders it as a bilingual PDF with the Hijri date', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity(IntegrationMode::Live);
    $payment = paidPayment($owner, $gateway);
    $receipt = PaymentReceipt::query()->sole();

    switchTo($owner)->getJson("/api/v1/payments/{$payment->public_id}/receipt")->assertOk()
        ->assertJsonPath('data.number', $receipt->number)
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.is_test', false)
        ->assertJsonPath('data.snapshot.total', ACCREDITED_TOTAL)
        ->assertJsonPath('data.snapshot.payment_reference', $payment->reference);

    $url = (string) $this->postJson("/api/v1/payments/{$payment->public_id}/receipt/download")->assertOk()
        ->assertJsonPath('data.file_name', $receipt->number.'.pdf')
        ->json('data.url');
    $download = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $document = app(FakePdfRenderer::class)->rendered()[0];
    expect($download->streamedContent())->toStartWith('%PDF-')
        ->and($document->template)->toBe(ReceiptDocumentBuilder::TEMPLATE)
        ->and($document->watermark)->toBeNull()
        ->and($document->data['number'])->toBe($receipt->number)
        ->and($document->data['issued_on_hijri']['ar'])->toMatch('/^\d{1,2} .+ \d{4}$/u')
        ->and($document->data['issued_on_hijri']['en'])->not->toMatch('/[\x{0660}-\x{0669}]/u')
        ->and($document->data['labels']['ar']['title'])->toBe('إيصال دفع')
        ->and($document->data['labels']['en']['title'])->toBe('Payment receipt')
        ->and(app(FakePdfRenderer::class)->rendered())->toHaveCount(1)
        ->and(Audit::query()->where('event', AuditEvent::FileDownloaded->value)->count())->toBe(1);
});

it('watermarks TEST receipts and VOID receipts after a recorded refund', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = paidPayment($owner, $gateway);
    $finance = financeOfficer();

    expect(app(FakePdfRenderer::class)->rendered()[0]->watermark)->toBe(ReceiptDocumentBuilder::TEST_WATERMARK);

    switchTo($finance)->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();

    $receipt = PaymentReceipt::query()->sole();
    $live = storedPaidPayment(null, IntegrationMode::Live);
    $liveReceipt = PaymentReceipt::query()->where('payment_id', $live->id)->sole();
    $liveReceipt->forceFill(['status' => 'voided', 'voided_at' => now(), 'void_reason' => REFUND_REASON])->save();

    switchTo($finance)->postJson("/api/v1/finance/receipts/{$liveReceipt->public_id}/download")->assertOk();

    expect($receipt->status->value)->toBe('voided')
        ->and(collect(app(FakePdfRenderer::class)->rendered())->last()?->watermark)->toBe(ReceiptDocumentBuilder::VOID_WATERMARK);
});

it('answers 404 before a receipt exists and hides other entities receipts', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $open = openedPayment($owner);
    $paid = paidPayment($owner, $gateway, 'bag');
    $other = ownerOf(entityWithOwner());

    switchTo($owner)->getJson("/api/v1/payments/{$open->public_id}/receipt")->assertNotFound()->assertJsonPath('code', 'receipt.not_issued');
    switchTo($other)->getJson("/api/v1/payments/{$paid->public_id}/receipt")->assertNotFound();
    $this->postJson("/api/v1/payments/{$paid->public_id}/receipt/download")->assertNotFound();

    $receipt = PaymentReceipt::query()->sole();
    $this->postJson("/api/v1/finance/receipts/{$receipt->public_id}/download")->assertNotFound();
});

it('lets the Finance Officer reissue a receipt with a reason and keeps the previous file', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity(IntegrationMode::Live);
    paidPayment($owner, $gateway);
    $receipt = PaymentReceipt::query()->sole();
    $firstFile = $receipt->pdf_file_id;

    switchTo(financeOfficer())->postJson("/api/v1/finance/receipts/{$receipt->public_id}/reissue", ['reason' => 'تحديث بيانات العرض في القالب المعتمد.'])
        ->assertOk()
        ->assertJsonPath('data.reissue_count', 1)
        ->assertJsonPath('data.number', $receipt->number);

    $receipt->refresh();
    expect($receipt->pdf_file_id)->not->toBe($firstFile)
        ->and(StoredFile::query()->whereKey($firstFile)->exists())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::ReceiptReissued->value)->value('reason'))->toBe('تحديث بيانات العرض في القالب المعتمد.');
});

it('refuses to reissue a voided receipt or without the receipts permission', function (): void {
    $payment = storedPaidPayment();
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    switchTo(systemAdmin())->postJson("/api/v1/finance/receipts/{$receipt->public_id}/reissue", ['reason' => 'إعادة إصدار للتحقق من القالب.'])->assertForbidden();
    switchTo(financeOfficer())->postJson("/api/v1/finance/receipts/{$receipt->public_id}/reissue", ['reason' => 'قصير'])->assertUnprocessable();

    $receipt->forceFill(['status' => 'voided', 'voided_at' => now(), 'void_reason' => REFUND_REASON])->save();
    $this->postJson("/api/v1/finance/receipts/{$receipt->public_id}/reissue", ['reason' => 'إعادة إصدار للتحقق من القالب.'])
        ->assertConflict()
        ->assertJsonPath('code', 'receipt.voided');
});

it('answers 503 for a receipt download while no PDF renderer is installed', function (): void {
    $payment = storedPaidPayment();
    app()->instance(PdfRenderer::class, new UnavailablePdfRenderer);
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    switchTo(financeOfficer())->postJson("/api/v1/finance/receipts/{$receipt->public_id}/download")
        ->assertStatus(503)
        ->assertJsonPath('code', 'document.renderer_unavailable');
});

it('never changes the number, snapshot or issue date of a receipt and never deletes it', function (): void {
    $payment = storedPaidPayment();
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    expect(fn () => PaymentReceipt::query()->whereKey($receipt->id)->update(['number' => 'RCP-2026-999999']))->toThrow(QueryException::class);
});

it('lets staff without finance permissions download no receipts', function (): void {
    $payment = storedPaidPayment();
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    switchTo(staffUser(RoleKey::Assessor))->postJson("/api/v1/finance/receipts/{$receipt->public_id}/download")->assertForbidden();
});
