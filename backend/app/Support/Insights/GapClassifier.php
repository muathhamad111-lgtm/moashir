<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\ClassifiedMain;
use App\Data\Insights\ClassifiedSub;
use App\Data\Insights\ResultMain;
use App\Data\Insights\ResultTree;
use App\Data\Insights\StrengthsGaps;
use App\Enums\Insights\GapClass;
use App\Support\ValueObjects\GapThresholds;

final class GapClassifier
{
    public function classify(ResultTree $tree, GapThresholds $rules): StrengthsGaps
    {
        $byClass = array_fill_keys(GapClass::values(), []);

        foreach ($tree->mains as $main) {
            foreach ($main->subs as $sub) {
                $class = $rules->classify($sub->figures->percent);
                $byClass[$class->value][] = new ClassifiedSub($main, $sub, $class);
            }
        }

        return new StrengthsGaps(
            $rules,
            array_map(static fn (ResultMain $main): ClassifiedMain => new ClassifiedMain($main, $rules->classify($main->figures->percent)), $tree->mains),
            $this->sorted($byClass[GapClass::Strength->value], $this->strongestFirst(...)),
            $this->sorted($byClass[GapClass::Opportunity->value], $this->mostLostFirst(...)),
            $this->sorted($byClass[GapClass::CriticalGap->value], $this->mostLostFirst(...)),
        );
    }

    /**
     * @param  list<ClassifiedSub>  $items
     * @param  callable(ClassifiedSub, ClassifiedSub): int  $order
     * @return list<ClassifiedSub>
     */
    private function sorted(array $items, callable $order): array
    {
        usort($items, $order);

        return $items;
    }

    private function mostLostFirst(ClassifiedSub $left, ClassifiedSub $right): int
    {
        return [$right->sub->figures->lostHundredths(), $left->sub->figures->percent->tenThousandths, ...$this->positionOf($left)]
            <=> [$left->sub->figures->lostHundredths(), $right->sub->figures->percent->tenThousandths, ...$this->positionOf($right)];
    }

    private function strongestFirst(ClassifiedSub $left, ClassifiedSub $right): int
    {
        return [$right->sub->figures->percent->tenThousandths, $right->sub->figures->weightHundredths(), ...$this->positionOf($left)]
            <=> [$left->sub->figures->percent->tenThousandths, $left->sub->figures->weightHundredths(), ...$this->positionOf($right)];
    }

    /**
     * @return array{int, int}
     */
    private function positionOf(ClassifiedSub $item): array
    {
        return [$item->main->position, $item->sub->position];
    }
}
