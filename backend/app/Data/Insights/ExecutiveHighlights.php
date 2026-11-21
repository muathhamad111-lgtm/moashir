<?php

declare(strict_types=1);

namespace App\Data\Insights;

final readonly class ExecutiveHighlights
{
    /**
     * @param  list<QuotedItem>  $strongAreas
     * @param  list<QuotedItem>  $priorityGaps
     */
    public function __construct(public array $strongAreas, public array $priorityGaps) {}
}
