<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Support\Assessments\PathPresenter;

final readonly class PathCount
{
    /**
     * @param  array<string, int>  $counts
     */
    public function __construct(public AssessmentPath $path, public array $counts) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...PathPresenter::of($this->path), ...$this->counts];
    }
}
