<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

final class YearlyNumberSequence
{
    private const string TABLE = 'yearly_number_sequences';

    public function next(string $series, int $year): int
    {
        return DB::transaction(static function () use ($series, $year): int {
            DB::table(self::TABLE)->upsert(
                [['series' => $series, 'year' => $year, 'last_value' => 1]],
                ['series', 'year'],
                ['last_value' => DB::raw(self::TABLE.'.last_value + 1')],
            );

            return ScalarValue::int(DB::table(self::TABLE)->where('series', $series)->where('year', $year)->value('last_value'));
        });
    }
}
