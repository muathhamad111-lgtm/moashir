<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Data\Prices\PriceChange;
use App\Data\Prices\PriceChangeOutcome;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Identity\User;
use App\Services\Prices\PriceEditAuthority;
use App\Services\Prices\PriceVersionWriter;
use Illuminate\Support\Facades\DB;

final readonly class SetPathPrice
{
    public function __construct(private PriceVersionWriter $writer, private PriceEditAuthority $authority) {}

    public function handle(User $actor, PaymentPurpose $purpose, PriceChange $change): PriceChangeOutcome
    {
        $this->authority->recordReason($actor, $change->reason);
        $outcome = DB::transaction(fn (): PriceChangeOutcome => $this->writer->write($actor, $purpose, $change));

        if ($outcome->changed) {
            $this->authority->afterChange($actor);
        }

        return $outcome;
    }
}
