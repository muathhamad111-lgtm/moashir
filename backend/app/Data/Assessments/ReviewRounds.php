<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class ReviewRounds
{
    /**
     * @param  list<AssessmentCompletionRequest>  $requests  oldest first; the first request opens round 2
     * @param  array<string, list<RoundScore>>  $units  keyed by the review input name, one score per round
     */
    public function __construct(public array $requests, public ?BoundSubCriteria $subs, public array $units) {}

    /**
     * Round 1 is the evaluation before any completion, so the request at position 0 opens round 2.
     */
    public static function roundAt(int $position): int
    {
        return $position + 2;
    }
}
