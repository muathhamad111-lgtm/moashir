<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\FinanceExportStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Finance\FinanceExport;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/**
 * @return array{entity: Entity, paid: Payment, bag: Payment, test: Payment, failed: Payment, refunded: Payment}
 */
function financeLedger(): array
{
    $entity = Entity::factory()->withOwner()->named('جمعية الواحة الخيرية', 'Al-Waha Charitable Association')->create();
    $refunded = storedPaidPayment(null, IntegrationMode::Live);
    $refunded->forceFill(['status' => PaymentStatus::Refunded, 'refunded_at' => now()])->save();

    return [
        'entity' => $entity,
        'paid' => storedPaidPayment($entity, IntegrationMode::Live),
        'bag' => storedPaidPayment(null, IntegrationMode::Live, PaymentPurpose::Bag),
        'test' => storedPaidPayment($entity, IntegrationMode::Test),
        'failed' => Payment::factory()->state(['status' => PaymentStatus::Failed, 'failed_at' => now(), 'failure_reason' => 'declined', 'provider_payment_id' => 'pay_failed_0001', 'bound_at' => now()])->create(),
        'refunded' => $refunded,
    ];
}

it('summarizes live collections by service without any gateway fee', function (): void {
    financeLedger();

    $response = switchTo(financeOfficer())->getJson('/api/v1/finance/summary')->assertOk()
        ->assertJsonPath('meta.live_only', true)
        ->assertJsonPath('data.transactions_count', 4)
        ->assertJsonPath('data.completed_count', 2)
        ->assertJsonPath('data.incomplete_count', 1)
        ->assertJsonPath('data.refunded_count', 1)
        ->assertJsonPath('data.total_income', '2300.00')
        ->assertJsonPath('data.total_vat', '300.00')
        ->assertJsonPath('data.total_refunded', '1150.00')
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.by_purpose.0.purpose', 'accredited')
        ->assertJsonPath('data.by_purpose.0.completed_count', 1)
        ->assertJsonPath('data.by_purpose.1.purpose', 'bag')
        ->assertJsonPath('data.by_purpose.1.completed_count', 1);

    expect((string) $response->getContent())->not->toContain('fee')->and((string) $response->getContent())->not->toContain('net');
    $this->getJson('/api/v1/finance/summary?from=2020-01-01&to=2020-12-31')->assertOk()->assertJsonPath('data.transactions_count', 0);
    $this->getJson('/api/v1/finance/summary?from=2026-12-01&to=2026-01-01')->assertUnprocessable();
});

it('lists live transactions with search, filters, sort and stats', function (): void {
    ['paid' => $paid, 'test' => $test] = financeLedger();
    switchTo(financeOfficer());

    $this->getJson('/api/v1/finance/transactions')->assertOk()
        ->assertJsonPath('meta.pagination.total', 4)
        ->assertJsonPath('meta.stats.total_income', '2300.00')
        ->assertJsonPath('meta.stats.transactions_count', 4);

    $this->getJson('/api/v1/finance/transactions?search='.urlencode('الواحه'))->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $paid->public_id)
        ->assertJsonPath('data.0.entity.name.en', 'Al-Waha Charitable Association')
        ->assertJsonPath('data.0.pricing.total', ACCREDITED_TOTAL)
        ->assertJsonPath('data.0.receipt_number', $paid->receipt?->number);

    $this->getJson('/api/v1/finance/transactions?search='.$paid->reference)->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/finance/transactions?search='.$paid->receipt?->number)->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/finance/transactions?filter[purpose]=bag')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('meta.stats.total_income', '1150.00');
    $this->getJson('/api/v1/finance/transactions?filter[status]=refunded')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/finance/transactions?filter[method]=mada')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson('/api/v1/finance/transactions?sort=-total')->assertOk();

    $this->getJson('/api/v1/finance/transactions?filter[mode]=test')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $test->public_id)
        ->assertJsonPath('data.0.is_test', true)
        ->assertJsonPath('meta.stats.transactions_count', 4);
});

it('filters transactions by entity and searches by the authorized person', function (): void {
    ['paid' => $paid] = financeLedger();
    $initiator = $paid->initiatedBy;
    switchTo(financeOfficer());

    $this->getJson("/api/v1/finance/transactions?filter[entity_id]={$paid->entity_id}")->assertOk()
        ->assertJsonPath('data.0.entity.name.en', 'Al-Waha Charitable Association');
    expect(collect($this->getJson("/api/v1/finance/transactions?filter[entity_id]={$paid->entity_id}")->json('data'))
        ->every(static fn (array $row): bool => $row['entity']['name']['en'] === 'Al-Waha Charitable Association'))->toBeTrue();
    $this->getJson('/api/v1/finance/transactions?filter[entity_id]=999999')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson('/api/v1/finance/transactions?search='.urlencode((string) $initiator?->name))->assertOk()
        ->assertJsonPath('data.0.entity.name.en', 'Al-Waha Charitable Association');
    $this->getJson('/api/v1/finance/transactions?filter[entity_id]=0')->assertUnprocessable();
});

it('rejects unknown sorts and filters', function (string $query): void {
    switchTo(financeOfficer())->getJson('/api/v1/finance/transactions?'.$query)->assertUnprocessable();
})->with([
    'sort' => ['sort=-fee'],
    'status' => ['filter[status]=settled'],
    'filter key' => ['filter[gateway_fee]=1'],
    'date' => ['filter[from]=01-11-2026'],
    'search' => ['search=%25'],
]);

it('shows the transaction detail with the recorded refund and its evidence file', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = paidPayment($owner, $gateway);
    $finance = financeOfficer();
    switchTo($finance)->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();

    $this->getJson("/api/v1/finance/transactions/{$payment->public_id}")->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.provider_payment_id', $payment->provider_payment_id)
        ->assertJsonPath('data.receipt.status', 'voided')
        ->assertJsonPath('data.refund.amount', ACCREDITED_TOTAL)
        ->assertJsonPath('data.refund.evidence.scan_status', 'clean')
        ->assertJsonPath('data.has_refund', true);
});

it('lets the System Administrator read financial operations and keeps everyone else out', function (): void {
    financeLedger();

    switchTo(systemAdmin())->getJson('/api/v1/finance/transactions')->assertOk();

    foreach ([staffUser(RoleKey::Assessor), staffUser(RoleKey::RequestFollower), staffUser(RoleKey::ContentOfficer), ownerOf(entityWithOwner())] as $user) {
        switchTo($user)->getJson('/api/v1/finance/transactions')->assertForbidden();
        $this->getJson('/api/v1/finance/summary')->assertForbidden();
        $this->postJson('/api/v1/finance/transactions/export')->assertForbidden();
    }
});

it('streams an audited CSV export of live transactions only', function (): void {
    ['paid' => $paid, 'test' => $test] = financeLedger();
    $paid->forceFill(['entity_name' => ['ar' => '=جمعية الأمل الخيرية', 'en' => 'Al-Amal']])->save();
    $finance = financeOfficer();

    $response = switchTo($finance)->post('/api/v1/finance/transactions/export', [], ['Accept' => 'application/json'])->assertOk()
        ->assertHeader('X-Export-Row-Count', '4');
    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\u{FEFF}")
        ->and($csv)->toContain($paid->reference)
        ->and($csv)->not->toContain($test->reference)
        ->and($csv)->toContain("'=جمعية الأمل الخيرية")
        ->and($csv)->toContain('مرجع الدفع')
        ->and(FinanceExport::query()->sole()->status)->toBe(FinanceExportStatus::Streamed)
        ->and(Audit::query()->where('event', AuditEvent::FinanceExported->value)->count())->toBe(1);
});

it('prepares a large export in the background and notifies the requester', function (): void {
    financeLedger();
    Config::set('moashir.finance.export_sync_max_rows', 2);
    $finance = financeOfficer();

    $exportId = (string) switchTo($finance)->postJson('/api/v1/finance/transactions/export')->assertAccepted()
        ->assertJsonPath('data.row_count', 4)
        ->json('data.id');

    $this->getJson("/api/v1/finance/exports/{$exportId}")->assertOk()->assertJsonPath('data.status', 'ready');
    $url = (string) $this->postJson("/api/v1/finance/exports/{$exportId}/download")->assertOk()->json('data.url');
    $csv = $this->get($url)->assertOk()->streamedContent();

    expect(substr_count($csv, "\n"))->toBe(5)
        ->and(financeNoticeSent($finance, NotificationCode::FinanceExportReady))->toBeTrue();

    switchTo(systemAdmin())->getJson("/api/v1/finance/exports/{$exportId}")->assertNotFound();
    $this->postJson("/api/v1/finance/exports/{$exportId}/download")->assertNotFound();
});
