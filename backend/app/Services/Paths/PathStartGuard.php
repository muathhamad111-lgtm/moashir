<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\Paths\PathRejected;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Assessments\AccreditedPurchaseCheck;
use App\Services\Assessments\SelfPurchaseCheck;
use LogicException;

final readonly class PathStartGuard
{
    public function __construct(
        private PathAvailabilityGuard $availability,
        private SelfPurchaseCheck $self,
        private AccreditedPurchaseCheck $accredited,
    ) {}

    public function assertMayStartFree(Entity $entity, PaymentPurpose $path, PathPricing $pricing): void
    {
        $this->availability->assertAvailable($path, $pricing);

        if ($pricing->requiresPayment()) {
            throw PathRejected::paymentRequired($path, $pricing);
        }

        $openOrder = Payment::query()->ownedBy($entity->id)->where('purpose', $path)->open()->latest('id')->first();

        if ($openOrder instanceof Payment) {
            throw PaymentRejected::openOrderExists($openOrder);
        }

        match ($path) {
            PaymentPurpose::SelfAssessment => $this->self->assertMayStart($entity),
            PaymentPurpose::Accredited => $this->accredited->assertMayStart($entity),
            default => throw new LogicException('Only the self and accredited paths start without a cycle.'),
        };
    }
}
