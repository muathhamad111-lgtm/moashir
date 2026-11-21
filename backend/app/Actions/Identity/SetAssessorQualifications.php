<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\PermissionKey;
use App\Exceptions\Identity\PathAccessRejected;
use App\Exceptions\Identity\RoleAssignmentRejected;
use App\Models\Identity\User;
use App\Models\Identity\UserAssessorQualification;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SetAssessorQualifications
{
    public function __construct(private StaffActionReason $reason, private AuditRecorder $audit) {}

    /**
     * @param  list<AssessorQualification>  $qualifications
     */
    public function handle(User $actor, User $target, array $qualifications, ?string $reason): User
    {
        $this->assertAssignable($actor, $target, $qualifications);
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor, $target, $qualifications): void {
            User::query()->whereKey($target->id)->lockForUpdate()->first();
            $target->unsetRelation('assessorQualifications');
            $before = $this->valuesOf($target->qualifications());
            $wanted = $this->valuesOf($qualifications);

            $target->assessorQualifications()->whereNotIn('qualification', $wanted)->delete();

            foreach (array_diff($wanted, $before) as $added) {
                UserAssessorQualification::query()->create(['user_id' => $target->id, 'qualification' => $added, 'granted_by_user_id' => $actor->id]);
            }

            $target->unsetRelation('assessorQualifications');
            $after = $this->valuesOf($target->qualifications());

            if ($before !== $after) {
                $this->audit->record(new AuditEntry(AuditEvent::UserAssessorQualificationsChanged, $target, ['qualifications' => $before], ['qualifications' => $after]));
            }
        });

        return $target;
    }

    /**
     * @param  list<AssessorQualification>  $qualifications
     */
    private function assertAssignable(User $actor, User $target, array $qualifications): void
    {
        if ($actor->is($target)) {
            throw RoleAssignmentRejected::selfGrant();
        }

        if ($target->isEntityUser() || ($qualifications !== [] && ! $target->can(PermissionKey::AssessmentScoreManage->value))) {
            throw PathAccessRejected::notAssessor();
        }
    }

    /**
     * @param  list<AssessorQualification>  $qualifications
     * @return list<string>
     */
    private function valuesOf(array $qualifications): array
    {
        $values = array_map(static fn (AssessorQualification $qualification): string => $qualification->value, $qualifications);
        sort($values);

        return array_values(array_unique($values));
    }
}
