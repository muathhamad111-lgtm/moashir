<?php

declare(strict_types=1);

namespace App\Support\Paths;

use App\Data\Paths\NextStep;

final class NextStepPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(NextStep $step): array
    {
        return [
            'rank' => $step->key->rank(),
            'key' => $step->key->value,
            'path' => $step->path->value,
            'target' => $step->target->toArray(),
            'context' => $step->context,
        ];
    }
}
