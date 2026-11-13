<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Exceptions\Identity\PathAccessRejected;
use App\Exceptions\Identity\RoleAssignmentRejected;
use App\Models\Identity\User;
use App\Models\Identity\UserRequestPath;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SetRequestPathScope
{
    private const string ALL_PATHS = 'all';

    public function __construct(private StaffActionReason $reason, private AuditRecorder $audit) {}

    /**
     * @param  list<AssessmentPath>|null  $paths
     */
    public function handle(User $actor, User $target, ?array $paths, ?string $reason): User
    {
        $this->assertAssignable($actor, $target, $paths);
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor, $target, $paths): void {
            User::query()->whereKey($target->id)->lockForUpdate()->first();
            $target->unsetRelation('requestPaths');
            $current = $target->grantedRequestPaths();
            $wanted = array_map(static fn (AssessmentPath $path): string => $path->value, $paths ?? []);

            $target->requestPaths()->whereNotIn('path', $wanted)->delete();

            foreach (array_diff($wanted, array_map(static fn (AssessmentPath $path): string => $path->value, $current)) as $added) {
                UserRequestPath::query()->create(['user_id' => $target->id, 'path' => $added, 'granted_by_user_id' => $actor->id]);
            }

            $target->unsetRelation('requestPaths');
            $before = $this->describe($current);
            $after = $this->describe($target->grantedRequestPaths());

            if ($before !== $after) {
                $this->audit->record(new AuditEntry(AuditEvent::UserRequestPathsChanged, $target, ['request_paths' => $before], ['request_paths' => $after]));
            }
        });

        return $target;
    }

    /**
     * @param  list<AssessmentPath>|null  $paths
     */
    private function assertAssignable(User $actor, User $target, ?array $paths): void
    {
        if ($actor->is($target)) {
            throw RoleAssignmentRejected::selfGrant();
        }

        if ($target->isEntityUser() || ($paths !== null && ! $target->can(PermissionKey::RequestsQueueView->value))) {
            throw PathAccessRejected::notFollower();
        }
    }

    /**
     * @param  list<AssessmentPath>  $granted
     * @return list<string>
     */
    private function describe(array $granted): array
    {
        if ($granted === []) {
            return [self::ALL_PATHS];
        }

        $values = array_map(static fn (AssessmentPath $path): string => $path->value, $granted);
        sort($values);

        return $values;
    }
}
