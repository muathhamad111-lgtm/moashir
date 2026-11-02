<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class EligibleAssessor
{
    public function __construct(public int $id, public string $name, public int $openRequests) {}
}
