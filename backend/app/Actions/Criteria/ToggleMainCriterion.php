<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;

final readonly class ToggleMainCriterion
{
    public function __construct(private DraftCriteriaTransaction $transaction, private CriteriaAuditTrail $audit) {}

    public function handle(User $actor, MainCriterion $main, bool $isActive): MainCriterion
    {
        return $this->transaction->run($main->criteria_version_id, $actor, function () use ($main, $isActive): MainCriterion {
            $main->refresh();

            if ($main->is_active !== $isActive) {
                $main->is_active = $isActive;
                $main->save();
                $this->audit->record(AuditEvent::CriteriaMainToggled, $main, ['active' => ! $isActive], ['active' => $isActive]);
            }

            return $main;
        });
    }
}
