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

final class AccreditedPurchaseCheck implements PurchaseEligibilityCheck
{
    public function assertMayPurchase(Entity $entity, OrderTerms $terms): void
    {
        if ($terms->purpose === PaymentPurpose::Accredited) {
            $this->assertMayStart($entity);
        }
    }

    public function assertMayStart(Entity $entity): void
    {
        $openId = Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::Accredited)->open()->value('id');

        if ($openId !== null) {
            throw AssessmentRejected::accreditedRequestOpen(ScalarValue::int($openId));
        }

        if ($this->hasPaidPaymentWithoutRequest($entity)) {
            throw AssessmentRejected::accreditedPaymentPending();
        }
    }

    private function hasPaidPaymentWithoutRequest(Entity $entity): bool
    {
        return Payment::query()
            ->ownedBy($entity->id)
            ->where('purpose', PaymentPurpose::Accredited)
            ->where('status', PaymentStatus::Paid)
            ->whereNotIn('id', Assessment::query()->ofPath(AssessmentPath::Accredited)->ownedBy($entity->id)->whereNotNull('payment_id')->select('payment_id'))
            ->exists();
    }
}
