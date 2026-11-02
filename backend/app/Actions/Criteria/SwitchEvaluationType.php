<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\EvaluationType;
use App\Events\Criteria\EvaluationTypeSwitched;
use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Support\StringList;

final readonly class SwitchEvaluationType
{
    public function __construct(private DraftCriteriaTransaction $transaction, private CriteriaAuditTrail $audit) {}

    public function handle(User $actor, SubCriterion $sub, EvaluationType $type, bool $confirmDeletePoints): SubCriterion
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($actor, $sub, $type, $confirmDeletePoints): SubCriterion {
            $sub->refresh();

            if ($sub->evaluation_type === $type) {
                return $sub;
            }

            $previous = $sub->evaluation_type;
            $removedCodes = $type === EvaluationType::Direct ? $this->removePoints($sub, $confirmDeletePoints) : [];

            $sub->evaluation_type = $type;
            $sub->save();

            $this->audit->record(AuditEvent::CriteriaSubTypeSwitched, $sub, ['evaluation_type' => $previous->value], [
                'evaluation_type' => $type->value,
                'removed_point_codes' => $removedCodes,
            ]);
            EvaluationTypeSwitched::dispatch($sub, $removedCodes, $actor->id);

            return $sub;
        });
    }

    /**
     * @return list<string>
     */
    private function removePoints(SubCriterion $sub, bool $confirmed): array
    {
        $codes = StringList::from($sub->guidingPoints()->pluck('code')->all());

        if ($codes !== [] && ! $confirmed) {
            throw CriteriaTreeRejected::pointsConfirmationRequired(count($codes));
        }

        $sub->guidingPoints()->delete();

        return $codes;
    }
}
