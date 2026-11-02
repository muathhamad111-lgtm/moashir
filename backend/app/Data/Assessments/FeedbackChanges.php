<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class FeedbackChanges
{
    /**
     * @param  array<array-key, array<string, string|null>>  $fieldsBySubId
     */
    public function __construct(public array $fieldsBySubId) {}
}
