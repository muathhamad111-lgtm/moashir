<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ReviewNoteChanges
{
    /**
     * @param  list<ReviewNoteChange>  $entries
     */
    public function __construct(public array $entries) {}
}
