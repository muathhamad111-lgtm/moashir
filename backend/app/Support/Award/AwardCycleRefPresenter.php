<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Models\Award\AwardCycle;

final class AwardCycleRefPresenter
{
    /**
     * @return array{id: int, name: array{ar: string, en: string}}|null
     */
    public static function of(?AwardCycle $cycle): ?array
    {
        return $cycle instanceof AwardCycle ? ['id' => $cycle->id, 'name' => $cycle->name->toArray()] : null;
    }
}
