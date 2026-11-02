<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Models\Award\AwardCycle;

final class CyclePresenter
{
    /**
     * @return array{id: int, name: array{ar: string, en: string}, status: string}|null
     */
    public static function summary(?AwardCycle $cycle): ?array
    {
        return $cycle instanceof AwardCycle ? ['id' => $cycle->id, 'name' => $cycle->name->toArray(), 'status' => $cycle->status->value] : null;
    }
}
