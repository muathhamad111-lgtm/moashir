<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AccreditedCurrent;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Assessments\SubmissionProgressReader;
use App\Services\Paths\ApplyReadinessCheck;
use App\Services\Paths\PathPricingReader;
use App\Services\Paths\SubmissionReadinessCheck;
use App\Services\Periods\AccreditedPeriodSummary;

final readonly class GetAccreditedCurrent
{
    public function __construct(
        private SubmissionProgressReader $progress,
        private AccreditedPeriodSummary $periods,
        private PathPricingReader $pricing,
        private SubmissionReadinessCheck $submission,
        private ApplyReadinessCheck $apply,
    ) {}

    public function handle(User $actor): AccreditedCurrent
    {
        $entity = Entity::withTrashed()->find($actor->entity_id) ?? throw AssessmentRejected::entityRequired();
        $requests = Assessment::query()->with(['entity', 'currentResult', 'period', 'payment.receipt', 'certificate'])->ownedBy($entity->id)->ofPath(AssessmentPath::Accredited);
        $active = (clone $requests)->open()->latest('id')->first();
        $awaitingPayment = Payment::query()->ownedBy($entity->id)->where('purpose', PaymentPurpose::Accredited)->open()->latest('id')->first();
        $progress = $active instanceof Assessment ? $this->progress->read($active) : null;
        $period = $this->periods->of($entity);
        $pricing = $this->pricing->accredited();

        return new AccreditedCurrent(
            $awaitingPayment,
            $active,
            (clone $requests)->whereNotIn('status', AssessmentPath::Accredited->openStateValues())->latest('id')->first(),
            $progress,
            $period,
            $this->submission->check($active, $progress, $period),
            $this->apply->check($awaitingPayment, $active, $pricing, $period),
            $pricing,
        );
    }
}
