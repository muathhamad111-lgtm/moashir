<?php

declare(strict_types=1);

use App\Services\Finance\ReceiptNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

const NUMBERING_YEAR = 2099;

function receiptNumberAt(string $connection): string
{
    DB::setDefaultConnection($connection);

    return app(ReceiptNumberGenerator::class)->next(CarbonImmutable::now());
}

it('numbers receipts per Riyadh year in the RCP-YYYY-###### format', function (): void {
    $generator = app(ReceiptNumberGenerator::class);

    $numbers = DB::transaction(static fn (): array => [
        $generator->next(CarbonImmutable::parse('2026-12-31 20:59:00', 'UTC')),
        $generator->next(CarbonImmutable::parse('2026-12-31 21:00:00', 'UTC')),
        $generator->next(CarbonImmutable::parse('2027-01-01 09:00:00', 'UTC')),
    ]);

    expect($numbers)->toBe(['RCP-2026-000001', 'RCP-2027-000001', 'RCP-2027-000002']);
});

it('leaves no gap when the issuing transaction rolls back', function (): void {
    $generator = app(ReceiptNumberGenerator::class);

    DB::beginTransaction();
    $discarded = $generator->next(CarbonImmutable::now());
    DB::rollBack();

    expect(DB::transaction(static fn (): string => $generator->next(CarbonImmutable::now())))->toBe($discarded);
});

it('refuses to allocate a number outside the issuing transaction', function (): void {
    DB::commit();

    try {
        expect(fn () => app(ReceiptNumberGenerator::class)->next(CarbonImmutable::now()))->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

it('makes a concurrent issuer wait for the first transaction and then continues without a gap', function (): void {
    $this->travelTo(CarbonImmutable::create(NUMBERING_YEAR, 3, 1, 9));
    $default = DB::getDefaultConnection();
    $settings = config('database.connections.'.$default);
    config(['database.connections.receipts_first' => $settings, 'database.connections.receipts_second' => $settings]);

    try {
        DB::connection('receipts_first')->beginTransaction();
        $first = receiptNumberAt('receipts_first');

        DB::connection('receipts_second')->statement("SET lock_timeout = '300ms'");
        DB::connection('receipts_second')->beginTransaction();
        $blocked = false;

        try {
            receiptNumberAt('receipts_second');
        } catch (QueryException) {
            $blocked = true;
        }

        DB::connection('receipts_second')->rollBack();
        DB::connection('receipts_first')->commit();

        DB::connection('receipts_second')->beginTransaction();
        $second = receiptNumberAt('receipts_second');
        DB::connection('receipts_second')->commit();

        expect($blocked)->toBeTrue()
            ->and($first)->toBe('RCP-'.NUMBERING_YEAR.'-000001')
            ->and($second)->toBe('RCP-'.NUMBERING_YEAR.'-000002');
    } finally {
        DB::connection('receipts_first')->table('yearly_number_sequences')->where('year', NUMBERING_YEAR)->delete();
        DB::setDefaultConnection($default);
        DB::purge('receipts_first');
        DB::purge('receipts_second');
    }
});
