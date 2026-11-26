<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Finance\Payment;
use App\Models\Platform\DemoRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bank transfers that entities submitted while transfers still opened in test mode (rule BT-3
 * before 2026-11-19). The fictional demo entities keep their test data.
 */
final readonly class TestBankTransferCandidates
{
    /**
     * @return Builder<Payment>
     */
    public function query(): Builder
    {
        return Payment::query()
            ->where('channel', PaymentChannel::BankTransfer)
            ->where('mode', IntegrationMode::Test)
            ->whereNotIn('entity_id', DemoRecord::query()->where('kind', DemoRecordKind::Entity)->select('record_id'));
    }
}
