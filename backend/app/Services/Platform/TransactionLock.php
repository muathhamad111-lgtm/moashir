<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use LogicException;

final class TransactionLock
{
    public function lock(string $name): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A transaction lock is taken inside a transaction only.');
        }

        DB::select('select pg_advisory_xact_lock(hashtext(?))', [$name]);
    }
}
