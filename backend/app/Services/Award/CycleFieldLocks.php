<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CycleAttributes;
use App\Enums\Award\AwardCycleStatus;
use App\Exceptions\Award\AwardRejected;
use App\Models\Identity\User;

final readonly class CycleFieldLocks
{
    private const array DRAFT_ONLY = ['submission_start', 'criteria_version_id', 'scale_kind'];

    private const array UNTIL_SUBMISSIONS_CLOSE = ['submission_end', 'region_ids', 'min_entity_age_years', 'disqualification_score'];

    private const array UNTIL_COMMITTEE = ['committee_start', 'committee_end', 'initial_review_sla_days'];

    public function __construct(private CycleRecordScope $scope) {}

    public function assertEditable(User $actor, AwardCycleStatus $status, CycleAttributes $changes): void
    {
        if ($changes->participationCertificateEnabled !== null && ! $this->scope->managesEveryCycle($actor)) {
            throw AwardRejected::participationSettingReserved();
        }

        $this->assertStageAllows($status, $changes->touchedFields());
    }

    /**
     * @param  list<string>  $fields
     */
    public function assertStageAllows(AwardCycleStatus $status, array $fields): void
    {
        $locked = array_values(array_filter($fields, fn (string $field): bool => ! $this->isEditable($status, $field)));

        if ($locked !== []) {
            throw AwardRejected::fieldsLocked($locked);
        }
    }

    private function isEditable(AwardCycleStatus $status, string $field): bool
    {
        return match (true) {
            $status === AwardCycleStatus::Closed => false,
            in_array($field, self::DRAFT_ONLY, true) => $status === AwardCycleStatus::Draft,
            in_array($field, self::UNTIL_SUBMISSIONS_CLOSE, true) => $status->acceptsConditionChanges(),
            in_array($field, self::UNTIL_COMMITTEE, true) => $status->acceptsDateChanges(),
            default => true,
        };
    }
}
