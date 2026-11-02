<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum RankStage: string
{
    case Regional = 'regional';
    case National = 'national';

    public function column(): string
    {
        return $this->value.'_rank';
    }
}
