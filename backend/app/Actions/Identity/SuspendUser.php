<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Identity\AccountActionRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountStatusChangedNotification;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\UserSessionTerminator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SuspendUser
{
    public function __construct(
        private RequestContext $context,
        private UserSessionTerminator $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, User $target, string $reason): User
    {
        $this->assertSuspendable($actor, $target);
        $this->context->recordReason($reason);

        DB::transaction(function () use ($target, $reason): void {
            $target->forceFill([
                'status' => UserStatus::Suspended,
                'deactivated_at' => Date::now(),
                'deactivation_reason' => $reason,
            ])->save();
            $this->sessions->terminateAll($target);
            $this->audit->record(new AuditEntry(AuditEvent::UserSuspended, $target, ['status' => UserStatus::Active->value], ['status' => UserStatus::Suspended->value]));
        });

        $target->notify(new AccountStatusChangedNotification(UserStatus::Suspended));

        return $target;
    }

    private function assertSuspendable(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw AccountActionRejected::selfTarget();
        }

        if ($target->isEntityUser()) {
            throw AccountActionRejected::entityStatusManaged();
        }

        if ($target->status !== UserStatus::Active) {
            throw IllegalStateTransition::from($target->effectiveStatus()->value, 'suspend');
        }
    }
}
