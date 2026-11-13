<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Contracts\Finance\PurchaseEligibilityCheck;
use App\Data\Finance\OrderTerms;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Award\AwardRejected;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use Illuminate\Support\Facades\Date;
use LogicException;

final readonly class AwardPurchaseCheck implements PurchaseEligibilityCheck
{
    public function __construct(private AwardEntryLedger $ledger) {}

    public function assertMayPurchase(Entity $entity, OrderTerms $terms): void
    {
        if ($terms->purpose !== PaymentPurpose::Award) {
            return;
        }

        $this->assertOpenFor($entity, $terms->cycle ?? throw new LogicException('An award order is always bound to a cycle.'));
    }

    public function assertOpenFor(Entity $entity, AwardCycle $cycle): void
    {
        if (! $cycle->acceptsApplicationsAt(Date::now())) {
            throw AwardRejected::submissionClosed();
        }

        $request = $this->ledger->requestIn($entity->id, $cycle->id);

        if ($request !== null) {
            throw AwardRejected::requestExists($request->id);
        }

        if ($this->ledger->hasPaidOrderAwaitingDraft($entity->id, $cycle->id)) {
            throw AwardRejected::paymentBeingProcessed();
        }
    }
}
