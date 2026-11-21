<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\AccreditedPathSummary;
use App\Data\Paths\AwardPathSummary;
use App\Data\Paths\SelfPathSummary;
use App\Enums\Award\AwardWindowState;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;

final class PaymentToSettleReader
{
    public function read(Entity $entity, SelfPathSummary $self, AccreditedPathSummary $accredited, AwardPathSummary $award): ?Payment
    {
        $candidates = array_filter([
            $this->candidate($this->latest($entity, PaymentPurpose::Accredited, null), $accredited->pricing->requiresPayment() && $accredited->pricing->isAvailable() && ! $accredited->openRequest instanceof Assessment),
            $this->candidate($this->latest($entity, PaymentPurpose::SelfAssessment, null), $self->pricing->requiresPayment() && $self->pricing->isAvailable() && ! $self->openDraft instanceof Assessment),
            $award->cycle instanceof AwardCycle ? $this->candidate($this->latest($entity, PaymentPurpose::Award, $award->cycle->id), $this->awardNeedsOrder($award)) : null,
        ]);

        usort($candidates, static fn (Payment $first, Payment $second): int => $second->id <=> $first->id);

        return $candidates[0] ?? null;
    }

    private function latest(Entity $entity, PaymentPurpose $purpose, ?int $cycleId): ?Payment
    {
        return Payment::query()->ownedBy($entity->id)->forService($purpose, $cycleId)->latest('id')->first();
    }

    private function candidate(?Payment $latest, bool $pathNeedsOrder): ?Payment
    {
        if (! $latest instanceof Payment) {
            return null;
        }

        $openCardOrder = $latest->channel === PaymentChannel::Electronic && in_array($latest->status, [PaymentStatus::Created, PaymentStatus::Pending], true);

        return $openCardOrder || ($latest->status === PaymentStatus::Rejected && $pathNeedsOrder) ? $latest : null;
    }

    private function awardNeedsOrder(AwardPathSummary $award): bool
    {
        return $award->window === AwardWindowState::Open
            && ! $award->request instanceof Assessment
            && $award->pricing->requiresPayment()
            && $award->pricing->isAvailable();
    }
}
