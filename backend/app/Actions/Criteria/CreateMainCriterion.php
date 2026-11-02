<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\MainCriterionAttributes;
use App\Enums\Audit\AuditEvent;
use App\Events\Criteria\MainCriterionCreated;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\DraftCriteriaTransaction;

final readonly class CreateMainCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaNodeWriter $writer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, MainCriterionAttributes $attributes, bool $isActive): MainCriterion
    {
        return $this->transaction->runOnCurrentDraft($actor, function (CriteriaVersion $draft) use ($actor, $attributes, $isActive): MainCriterion {
            $main = $this->writer->addMain($draft, $attributes, $isActive);

            $this->audit->created(AuditEvent::CriteriaMainCreated, $main);
            MainCriterionCreated::dispatch($main, $actor->id);

            return $main;
        });
    }
}
