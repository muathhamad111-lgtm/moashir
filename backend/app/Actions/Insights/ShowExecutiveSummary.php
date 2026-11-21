<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\ExecutiveSummaryView;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityLogoLink;

final readonly class ShowExecutiveSummary
{
    public function __construct(private GetExecutiveSummary $summaries, private EntityLogoLink $logos) {}

    public function handle(User $viewer, Assessment $assessment): ExecutiveSummaryView
    {
        $summary = $this->summaries->handle($assessment);

        return new ExecutiveSummaryView($summary, $this->logos->for($viewer, $summary->subject->result->assessment->entity));
    }
}
