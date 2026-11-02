<?php

declare(strict_types=1);

namespace App\Services\Settings;

use Illuminate\Support\Facades\DB;
use LogicException;

final class SettingsLock
{
    public function lock(string $section): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Settings sections are locked inside a transaction only.');
        }

        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['settings:'.$section]);
    }
}
