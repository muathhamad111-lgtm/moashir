<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\AccreditedPathSummary;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Assessments\SubmissionProgressReader;
use App\Services\Periods\AccreditedPeriodSummary;

final readonly class AccreditedPathReader
{
    public function __construct(
        private PathPricingReader $pricing,
        private AccreditedPeriodSummary $periods,
        private SubmissionProgressReader $progress,
        private ApplyReadinessCheck $apply,
        private PathListing $listing,
    ) {}

    public function read(Entity $entity): AccreditedPathSummary
    {
        $requests = Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::Accredited);
        $open = (clone $requests)->with(['period', 'openCompletionRequest', 'criteriaVersion'])->open()->latest('id')->first();
        $lastResult = (clone $requests)->with(['currentResult', 'period', 'certificate'])->where('status', AccreditedAssessmentStatus::Completed)
            ->latest('completed_at')->latest('id')->first();
        $awaitingPayment = Payment::query()->ownedBy($entity->id)->where('purpose', PaymentPurpose::Accredited)->open()->latest('id')->first();
        $pricing = $this->pricing->accredited();
        $period = $this->periods->of($entity);

        return new AccreditedPathSummary(
            $pricing,
            $period,
            $awaitingPayment,
            $open,
            $open instanceof Assessment ? $this->progress->read($open) : null,
            $lastResult,
            $this->apply->check($awaitingPayment, $open, $pricing, $period),
            $this->listing->isListed($entity, PaymentPurpose::Accredited, $pricing),
        );
    }
}
