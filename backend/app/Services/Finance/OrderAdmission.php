<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Award\AwardOrderGate;
use App\Services\Paths\PathAvailabilityGuard;
use App\Services\Paths\PathPricingReader;

final readonly class OrderAdmission
{
    public function __construct(
        private PathPricingReader $pricing,
        private PathAvailabilityGuard $availability,
        private AwardOrderGate $award,
    ) {}

    public function admit(User $actor, Entity $entity, PaymentPurpose $purpose, ?int $awardCycleId): ?AwardCycle
    {
        if (! $purpose->isPerCycle()) {
            $this->availability->assertAvailable($purpose, $this->pricing->of($purpose));

            return null;
        }

        $cycle = AwardCycle::query()->with('regions')->find($awardCycleId) ?? throw PaymentRejected::awardCycleNotFound();
        $this->availability->assertAvailable($purpose, $this->pricing->award($cycle));
        $this->award->admit($actor, $entity, $cycle);

        return $cycle;
    }
}
