<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Collection;

final class CriteriaCodeAllocator
{
    private const string MAIN_PREFIX = 'M';

    public function nextMainCode(): string
    {
        return $this->next(self::MAIN_PREFIX, MainCriterion::query()->where('code', 'like', self::MAIN_PREFIX.'%')->pluck('code'));
    }

    public function nextSubCode(MainCriterion $main): string
    {
        $prefix = $main->code.'.';

        return $this->next($prefix, SubCriterion::query()->where('code', 'like', $prefix.'%')->pluck('code'));
    }

    public function nextPointCode(SubCriterion $sub): string
    {
        $prefix = $sub->code.'.';

        return $this->next($prefix, GuidingPoint::query()->where('code', 'like', $prefix.'%')->pluck('code'));
    }

    /**
     * @param  Collection<int, mixed>  $usedCodes
     */
    private function next(string $prefix, Collection $usedCodes): string
    {
        $highest = 0;

        foreach ($usedCodes as $code) {
            $suffix = is_string($code) ? substr($code, strlen($prefix)) : '';

            if (ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $prefix.($highest + 1);
    }
}
