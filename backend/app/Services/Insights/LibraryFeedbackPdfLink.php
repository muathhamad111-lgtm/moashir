<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Identity\Ability;
use App\Enums\Identity\Locale;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Reports\FeedbackReportPdfLink;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;

final readonly class LibraryFeedbackPdfLink
{
    public function __construct(private FeedbackReportPdfLink $links, private Gate $gate, private Repository $config) {}

    public function for(User $viewer, Assessment $assessment): ?string
    {
        if ($assessment->status !== AccreditedAssessmentStatus::Completed || $this->gate->forUser($viewer)->denies(Ability::ViewFeedbackReport->value, $assessment)) {
            return null;
        }

        return $this->links->issue($viewer, $assessment, $this->locale())->url;
    }

    private function locale(): Locale
    {
        return Locale::tryFrom($this->config->string('app.locale')) ?? Locale::Arabic;
    }
}
