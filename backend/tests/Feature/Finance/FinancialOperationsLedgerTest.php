<?php

declare(strict_types=1);

use App\Actions\Finance\GenerateTransactionsExport;
use App\Enums\Finance\FinanceExportStatus;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Finance\FinanceExport;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const LEDGER = '/api/v1/finance/transactions';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/**
 * Six live transactions of 1150.00 each: three electronic (paid, created, pending), two bank
 * transfers (paid, under review) and one refunded electronic payment.
 *
 * @return array{paid: Payment, created: Payment, pending: Payment, transfer: Payment, review: Payment, refunded: Payment}
 */
function operationsLedger(): array
{
    $refunded = storedPaidPayment();
    $refunded->forceFill(['status' => PaymentStatus::Refunded, 'refunded_at' => now()])->save();

    return [
        'paid' => storedPaidPayment(),
        'created' => Payment::factory()->create(),
        'pending' => Payment::factory()->create(['status' => PaymentStatus::Pending, 'provider_payment_id' => 'pay_ledger_pending', 'bound_at' => now()]),
        'transfer' => Payment::factory()->bankTransfer()->create(['status' => PaymentStatus::Paid, 'paid_at' => now()]),
        'review' => Payment::factory()->bankTransfer()->create(),
        'refunded' => $refunded,
    ];
}

/**
 * @return list<string>
 */
function ledgerIds(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('id')->sort()->values()->all();
}

/**
 * @param  list<Payment>  $payments
 * @return list<string>
 */
function publicIdsOf(array $payments): array
{
    return collect($payments)->pluck('public_id')->sort()->values()->all();
}

function transactionEventsUrl(Payment|string $payment): string
{
    return LEDGER.'/'.($payment instanceof Payment ? $payment->public_id : $payment).'/events';
}

/**
 * @param  array<string, mixed>  $attributes
 */
function recordedPaymentEvent(Payment $payment, Carbon $at, array $attributes = []): PaymentEvent
{
    $event = new PaymentEvent([
        'payment_id' => $payment->id,
        'provider' => 'moyasar',
        'provider_event_id' => 'evt-'.Str::uuid()->toString(),
        'source' => PaymentEventSource::Webhook,
        'event_type' => 'payment_paid',
        'mode' => $payment->mode,
        'signature_valid' => true,
        'payload' => ['secret_token' => 'ledger-event-secret', 'source' => ['name' => 'Salman Al-Otaibi']],
        'ip_address' => '62.149.100.20',
        'processed_at' => $at,
        'processing_result' => PaymentEventResult::Applied,
        ...$attributes,
    ]);
    $event->forceFill(['created_at' => $at])->save();

    return $event;
}

it('types every row and filters by one or several types', function (): void {
    $ledger = operationsLedger();
    switchTo(financeOfficer());

    $rows = collect($this->getJson(LEDGER)->assertOk()->json('data'))->keyBy('id');

    expect($rows)->toHaveCount(6)
        ->and($rows[$ledger['paid']->public_id]['type'])->toBe('electronic')
        ->and($rows[$ledger['pending']->public_id]['type'])->toBe('electronic')
        ->and($rows[$ledger['transfer']->public_id]['type'])->toBe('bank_transfer')
        ->and($rows[$ledger['refunded']->public_id]['type'])->toBe('refund')
        ->and($rows[$ledger['refunded']->public_id]['channel'])->toBe('electronic');

    expect(ledgerIds($this->getJson(LEDGER.'?filter[type]=electronic')->assertOk()))
        ->toBe(publicIdsOf([$ledger['paid'], $ledger['created'], $ledger['pending']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type]=bank_transfer')->assertOk()))
        ->toBe(publicIdsOf([$ledger['transfer'], $ledger['review']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type]=refund')->assertOk()))
        ->toBe(publicIdsOf([$ledger['refunded']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type][]=refund')->assertOk()))
        ->toBe(publicIdsOf([$ledger['refunded']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type][]=electronic&filter[type][]=refund')->assertOk()))
        ->toBe(publicIdsOf([$ledger['paid'], $ledger['created'], $ledger['pending'], $ledger['refunded']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type][]=bank_transfer&filter[type][]=refund&filter[type][]=bank_transfer')->assertOk()))
        ->toBe(publicIdsOf([$ledger['transfer'], $ledger['review'], $ledger['refunded']]))
        ->and($this->getJson(LEDGER.'?filter[type][]=electronic&filter[type][]=bank_transfer&filter[type][]=refund')->assertOk()->json('meta.pagination.total'))
        ->toBe(6)
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type][]=bank_transfer&filter[type][]=refund&filter[status][]=paid')->assertOk()))
        ->toBe(publicIdsOf([$ledger['transfer']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type][]=electronic&filter[type][]=refund&search='.$ledger['pending']->reference)->assertOk()))
        ->toBe(publicIdsOf([$ledger['pending']]));
});

it('rejects unknown or malformed types', function (string $query, string $errorKey): void {
    switchTo(financeOfficer())->getJson(LEDGER.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors([$errorKey]);
})->with([
    'unknown single' => ['filter[type]=cash', 'filter.type.0'],
    'unknown in a list' => ['filter[type][]=refund&filter[type][]=cash', 'filter.type.1'],
    'keyed map' => ['filter[type][first]=refund', 'filter.type'],
    'nested array' => ['filter[type][0][]=refund', 'filter.type.0'],
    'too many values' => [implode('&', array_fill(0, 4, 'filter[type][]=refund')), 'filter.type'],
]);

it('filters by several statuses, the awaiting payment alias and a single status string', function (): void {
    $ledger = operationsLedger();
    switchTo(financeOfficer());

    expect(ledgerIds($this->getJson(LEDGER.'?filter[status][]=paid&filter[status][]=under_review')->assertOk()))
        ->toBe(publicIdsOf([$ledger['paid'], $ledger['transfer'], $ledger['review']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[status][]=awaiting_payment')->assertOk()))
        ->toBe(publicIdsOf([$ledger['created'], $ledger['pending']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[status]=awaiting_payment')->assertOk()))
        ->toBe(publicIdsOf([$ledger['created'], $ledger['pending']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[status][]=awaiting_payment&filter[status][]=refunded&filter[status][]=created')->assertOk()))
        ->toBe(publicIdsOf([$ledger['created'], $ledger['pending'], $ledger['refunded']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[status]=paid')->assertOk()))
        ->toBe(publicIdsOf([$ledger['paid'], $ledger['transfer']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type]=electronic&filter[status][]=paid')->assertOk()))
        ->toBe(publicIdsOf([$ledger['paid']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type]=bank_transfer&filter[status][]=refunded')->assertOk()))
        ->toBe([]);
});

it('rejects unknown or malformed statuses', function (string $query): void {
    switchTo(financeOfficer())->getJson(LEDGER.'?'.$query)->assertUnprocessable();
})->with([
    'unknown single' => ['filter[status]=settled'],
    'unknown in a list' => ['filter[status][]=paid&filter[status][]=settled'],
    'keyed map' => ['filter[status][first]=paid'],
    'nested array' => ['filter[status][0][]=paid'],
    'too many values' => [implode('&', array_fill(0, 10, 'filter[status][]=paid'))],
]);

it('filters by the creation date range in the display time zone, refunds included', function (): void {
    $ledger = operationsLedger();
    $ledger['created']->forceFill(['created_at' => Carbon::parse('2026-01-10 09:00:00', 'UTC')])->save();
    $ledger['refunded']->forceFill(['created_at' => Carbon::parse('2026-01-31 20:30:00', 'UTC')])->save();
    $ledger['review']->forceFill(['created_at' => Carbon::parse('2026-01-31 21:30:00', 'UTC')])->save();
    switchTo(financeOfficer());

    $january = $this->getJson(LEDGER.'?filter[from]=2026-01-01&filter[to]=2026-01-31')->assertOk()
        ->assertJsonPath('meta.stats.transactions_count', 2)
        ->assertJsonPath('meta.stats.transactions_value', '2300.00');

    expect(ledgerIds($january))->toBe(publicIdsOf([$ledger['created'], $ledger['refunded']]))
        ->and(ledgerIds($this->getJson(LEDGER.'?filter[type]=refund&filter[from]=2026-02-01')->assertOk()))->toBe([]);

    $this->getJson(LEDGER.'?filter[from]=2026-02-01&filter[to]=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors(['filter.to']);
});

it('computes the stats cards from every filter of the list', function (): void {
    $ledger = operationsLedger();
    switchTo(financeOfficer());

    $this->getJson(LEDGER)->assertOk()->assertJsonPath('meta.stats', [
        'transactions_count' => 6,
        'completed_count' => 2,
        'incomplete_count' => 3,
        'refunded_count' => 1,
        'total_income' => '2300.00',
        'total_vat' => '300.00',
        'total_refunded' => '1150.00',
        'transactions_value' => '6900.00',
        'currency' => 'SAR',
    ]);

    $this->getJson(LEDGER.'?filter[type]=bank_transfer')->assertOk()
        ->assertJsonPath('meta.stats.transactions_count', 2)
        ->assertJsonPath('meta.stats.total_income', '1150.00')
        ->assertJsonPath('meta.stats.transactions_value', '2300.00');
    $this->getJson(LEDGER.'?filter[status]=awaiting_payment')->assertOk()
        ->assertJsonPath('meta.stats.transactions_count', 2)
        ->assertJsonPath('meta.stats.total_income', '0.00')
        ->assertJsonPath('meta.stats.transactions_value', '2300.00');
    $this->getJson(LEDGER.'?filter[type]=refund')->assertOk()
        ->assertJsonPath('meta.stats.total_refunded', '1150.00')
        ->assertJsonPath('meta.stats.transactions_value', '1150.00');
    $this->getJson(LEDGER.'?filter[type][]=bank_transfer&filter[type][]=refund')->assertOk()
        ->assertJsonPath('meta.stats.transactions_count', 3)
        ->assertJsonPath('meta.stats.total_income', '1150.00')
        ->assertJsonPath('meta.stats.total_refunded', '1150.00')
        ->assertJsonPath('meta.stats.transactions_value', '3450.00');
    $this->getJson(LEDGER.'?search='.$ledger['transfer']->reference)->assertOk()
        ->assertJsonPath('meta.stats.transactions_count', 1)
        ->assertJsonPath('meta.stats.total_income', '1150.00');
    $this->getJson(LEDGER."?filter[entity_id]={$ledger['review']->entity_id}")->assertOk()
        ->assertJsonPath('meta.stats.transactions_count', 1)
        ->assertJsonPath('meta.stats.total_income', '0.00')
        ->assertJsonPath('meta.stats.transactions_value', '1150.00');
});

it('embeds the recorded refund on refunded rows without extra queries per row', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $accredited = paidPayment($owner, $gateway);
    $bag = paidPayment($owner, $gateway, 'bag');
    $finance = financeOfficer();
    $url = LEDGER.'?filter[mode]=test&filter[type]=refund';

    switchTo($finance)->post("/api/v1/finance/payments/{$accredited->public_id}/refunds", refundPayload($accredited), ['Accept' => 'application/json'])->assertCreated();
    $refundId = $accredited->refund()->sole()->public_id;

    $this->getJson(LEDGER.'?filter[mode]=test')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.id', $bag->public_id)
        ->assertJsonPath('data.0.type', 'electronic')
        ->assertJsonPath('data.0.refund', null);

    DB::enableQueryLog();
    $this->getJson($url)->assertOk()
        ->assertJsonPath('data.0.type', 'refund')
        ->assertJsonPath('data.0.has_refund', true)
        ->assertJsonPath('data.0.refund', [
            'id' => $refundId,
            'amount' => ACCREDITED_TOTAL,
            'currency' => 'SAR',
            'refunded_on' => $accredited->paid_at?->copy()->setTimezone('Asia/Riyadh')->toDateString(),
            'transfer_reference' => 'TRF-2026-55871',
            'recorded_by' => ['id' => $finance->id, 'name' => $finance->name],
            'has_evidence' => true,
        ]);
    $queriesForOneRefund = count(DB::getQueryLog());

    $this->post("/api/v1/finance/payments/{$bag->public_id}/refunds", refundPayload($bag), ['Accept' => 'application/json'])->assertCreated();
    DB::flushQueryLog();
    $this->getJson($url)->assertOk()->assertJsonPath('meta.pagination.total', 2)->assertJsonPath('data.1.refund.has_evidence', true);

    expect(count(DB::getQueryLog()))->toBe($queriesForOneRefund);

    $this->postJson("/api/v1/finance/refunds/{$refundId}/evidence/download")->assertOk()->assertJsonPath('data.file_name', 'bank-transfer.pdf');
    $this->getJson(LEDGER."/{$accredited->public_id}")->assertOk()
        ->assertJsonPath('data.type', 'refund')
        ->assertJsonPath('data.refund.id', $refundId)
        ->assertJsonPath('data.refund.has_evidence', true)
        ->assertJsonPath('data.refund.reason', REFUND_REASON)
        ->assertJsonPath('data.refund.evidence.scan_status', 'clean');
});

it('lists the events of one transaction newest first without payloads or full provider references', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $other = openedPayment(ownerOf(entityWithOwner()), 'bag');
    $providerId = providerPaymentOf($gateway, $payment);
    sendMoyasarWebhook(moyasarEventFor($payment, $providerId))->assertOk();
    $latest = recordedPaymentEvent($payment, now()->addMinutes(5), ['provider_payment_id' => $providerId, 'error_code' => 'amount_mismatch']);

    $response = switchTo(financeOfficer())->getJson(transactionEventsUrl($payment))->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.limit', 50)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('data.0', [
            'id' => $latest->id,
            'created_at' => $latest->created_at->toIso8601String(),
            'source' => 'webhook',
            'type' => 'payment_paid',
            'result' => 'applied',
            'mode' => 'test',
            'provider_reference' => '…'.substr($providerId, -6),
        ])
        ->assertJsonPath('data.2.source', 'checkout');
    $body = (string) $response->getContent();
    $events = collect($response->json('data'));

    expect($events->pluck('id')->all())->toBe(PaymentEvent::query()->where('payment_id', $payment->id)->orderByDesc('created_at')->orderByDesc('id')->pluck('id')->all())
        ->and($events->pluck('id'))->not->toContain(PaymentEvent::query()->where('payment_id', $other->id)->value('id'))
        ->and($body)->not->toContain($providerId)
        ->and($body)->not->toContain('ledger-event-secret')
        ->and($body)->not->toContain('62.149.100.20')
        ->and($body)->not->toContain('Salman')
        ->and($body)->not->toContain('payload')
        ->and($body)->not->toContain('amount_mismatch');
});

it('caps the events of one transaction at fifty and says more exist', function (): void {
    $payment = storedPaidPayment();
    foreach (range(1, 51) as $minute) {
        recordedPaymentEvent($payment, now()->subMinutes($minute));
    }

    switchTo(financeOfficer())->getJson(transactionEventsUrl($payment))->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.has_more', true);
});

it('keeps the transaction events away from anyone without the operations permission', function (): void {
    $payment = storedPaidPayment();
    recordedPaymentEvent($payment, now());

    switchTo(systemAdmin())->getJson(transactionEventsUrl($payment))->assertOk()->assertJsonCount(1, 'data');

    foreach ([staffUser(RoleKey::Assessor), staffUser(RoleKey::RequestFollower), staffUser(RoleKey::ContentOfficer), ownerOf(entityWithOwner())] as $user) {
        switchTo($user)->getJson(transactionEventsUrl($payment))->assertForbidden();
    }
});

it('asks a guest to sign in before reading transaction events', function (): void {
    $this->getJson(transactionEventsUrl(storedPaidPayment()))->assertUnauthorized();
});

it('answers not found for an unknown transaction', function (string $id): void {
    switchTo(financeOfficer())->getJson(transactionEventsUrl($id))->assertNotFound();
})->with([
    'unknown uuid' => [(string) Str::uuid()],
    'not a uuid' => ['12'],
]);

it('no longer serves the merged summary and refunds list pages', function (string $path): void {
    operationsLedger();

    switchTo(financeOfficer())->getJson('/api/v1/finance/'.$path)->assertNotFound();
})->with(['summary', 'refunds']);

it('exports exactly the rows the type and status filters select, in the background too', function (): void {
    operationsLedger();
    switchTo(financeOfficer());

    $this->post(LEDGER.'/export?filter[type]=electronic&filter[status][]=awaiting_payment', [], ['Accept' => 'application/json'])->assertOk()
        ->assertHeader('X-Export-Row-Count', '2');

    Config::set('moashir.finance.export_sync_max_rows', 1);
    $exportId = (string) $this->postJson(LEDGER.'/export?filter[type][]=bank_transfer&filter[type][]=refund&filter[status][]=paid&filter[status][]=under_review&filter[status][]=refunded')->assertAccepted()
        ->assertJsonPath('data.row_count', 3)
        ->json('data.id');
    $url = (string) $this->postJson("/api/v1/finance/exports/{$exportId}/download")->assertOk()->json('data.url');

    expect(substr_count($this->get($url)->assertOk()->streamedContent(), "\n"))->toBe(4)
        ->and(FinanceExport::query()->where('public_id', $exportId)->sole()->filters)
        ->toMatchArray(['type' => ['bank_transfer', 'refund'], 'status' => ['paid', 'under_review', 'refunded']]);
});

it('still generates a background export saved with a single type and status string', function (): void {
    ['refunded' => $refunded, 'transfer' => $transfer] = operationsLedger();
    $finance = financeOfficer();
    $export = FinanceExport::query()->create([
        'requested_by_user_id' => $finance->id,
        'filters' => ['type' => 'refund', 'status' => 'refunded', 'mode' => 'live', 'sort' => '-created_at'],
        'row_count' => 1,
        'locale' => 'ar',
        'status' => FinanceExportStatus::Queued,
    ]);

    app(GenerateTransactionsExport::class)->handle($export->id);
    $url = (string) switchTo($finance)->postJson("/api/v1/finance/exports/{$export->public_id}/download")->assertOk()->json('data.url');
    $csv = $this->get($url)->assertOk()->streamedContent();

    expect(substr_count($csv, "\n"))->toBe(2)
        ->and($csv)->toContain($refunded->reference)
        ->and($csv)->not->toContain($transfer->reference);
});
