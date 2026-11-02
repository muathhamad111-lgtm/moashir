<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Models\Award\AwardCycle;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Percent;
use BackedEnum;
use Carbon\CarbonInterface;

final class CycleAuditValues
{
    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function of(AwardCycle $cycle, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $field === 'region_ids' ? $cycle->regions->modelKeys() : $this->scalar($cycle->getAttribute($field));
        }

        return $values;
    }

    private function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof CarbonInterface => $value->toIso8601String(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof Percent => $value->toDisplay(),
            $value instanceof LocalizedText => $value->toArray(),
            default => $value,
        };
    }
}
