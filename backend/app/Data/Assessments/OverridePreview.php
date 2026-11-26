<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class OverridePreview
{
    /**
     * @param  list<PreviewedScoreChange>  $changes  in criteria order
     */
    public function __construct(public PreviewedResult $before, public PreviewedResult $after, public array $changes) {}
}
