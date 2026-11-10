<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AdvancedEligibility;
use App\Exceptions\Award\AwardRejected;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AwardOrderGate
{
    public function __construct(
        private AwardPurchaseCheck $check,
        private AdvancedEligibilityChecker $eligibility,
        private AdvancedRejectionRecorder $rejections,
        private ConditionFailureMessages $messages,
    ) {}

    public function admit(User $actor, Entity $entity, AwardCycle $cycle): void
    {
        $rejection = DB::transaction(function () use ($actor, $entity, $cycle): ?AdvancedEligibility {
            Entity::query()->whereKey($entity->id)->lockForUpdate()->first();
            $this->check->assertOpenFor($entity, $cycle);
            $eligibility = $this->eligibility->check($entity, $cycle, Date::now());

            if ($eligibility->meetsConditions()) {
                return null;
            }

            $this->rejections->record($actor, $entity, $cycle, $eligibility);

            return $eligibility;
        });

        if ($rejection instanceof AdvancedEligibility) {
            throw AwardRejected::conditionsFailed($this->messages->fieldErrors($rejection), $rejection->failuresToArray());
        }
    }
}
