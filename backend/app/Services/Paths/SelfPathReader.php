<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\SelfPathSummary;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Assessments\SelfDraftProgressReader;

final readonly class SelfPathReader
{
    public function __construct(
        private PathPricingReader $pricing,
        private SelfDraftProgressReader $progress,
        private PathListing $listing,
    ) {}

    public function read(Entity $entity): SelfPathSummary
    {
        $requests = Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::SelfAssessment);
        $draft = (clone $requests)->with('criteriaVersion')->where('status', SelfAssessmentStatus::Draft)->latest('id')->first();
        $lastResult = (clone $requests)->with('currentResult')->where('status', SelfAssessmentStatus::ResultAvailable)->whereNotNull('current_result_snapshot_id')
            ->latest('completed_at')->latest('id')->first();

        $pricing = $this->pricing->self();

        return new SelfPathSummary(
            $pricing,
            $draft,
            $draft instanceof Assessment ? $this->progress->read($draft) : null,
            $lastResult,
            Payment::query()->ownedBy($entity->id)->where('purpose', PaymentPurpose::SelfAssessment)->open()->latest('id')->first(),
            $this->listing->isListed($entity, PaymentPurpose::SelfAssessment, $pricing),
        );
    }
}
