<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Finance\PurchaseEligibilityCheck;
use App\Data\Finance\OrderTerms;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Support\ScalarValue;

final class SelfPurchaseCheck implements PurchaseEligibilityCheck
{
    public function assertMayPurchase(Entity $entity, OrderTerms $terms): void
    {
        if ($terms->purpose === PaymentPurpose::SelfAssessment) {
            $this->assertMayStart($entity);
        }
    }

    public function assertMayStart(Entity $entity): void
    {
        $draftId = Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::SelfAssessment)->open()->value('id');

        if ($draftId !== null) {
            throw AssessmentRejected::selfDraftExists(ScalarValue::int($draftId));
        }

        if ($this->hasPaidPaymentWithoutDraft($entity)) {
            throw AssessmentRejected::selfPaymentPending();
        }
    }

    private function hasPaidPaymentWithoutDraft(Entity $entity): bool
    {
        return Payment::query()
            ->ownedBy($entity->id)
            ->where('purpose', PaymentPurpose::SelfAssessment)
            ->where('status', PaymentStatus::Paid)
            ->whereNotIn('id', Assessment::query()->ofPath(AssessmentPath::SelfAssessment)->ownedBy($entity->id)->whereNotNull('payment_id')->select('payment_id'))
            ->exists();
    }
}
