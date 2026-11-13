<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Assessments\AssessmentPath;

final readonly class OpenedRequest
{
    public function __construct(public int $id, public string $number, public AssessmentPath $path) {}
}
