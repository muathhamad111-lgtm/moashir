<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\UserStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountStatusChangedNotification;
use App\Services\Audit\AuditRecorder;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class ReactivateUser
{
    public function __construct(private RequestContext $context, private AuditRecorder $audit) {}

    public function handle(User $target, ?string $reason): User
    {
        if ($target->status !== UserStatus::Suspended) {
            throw IllegalStateTransition::from($target->effectiveStatus()->value, 'activate');
        }

        $this->context->recordReason($reason);

        DB::transaction(function () use ($target): void {
            $target->forceFill(['status' => UserStatus::Active, 'deactivated_at' => null, 'deactivation_reason' => null])->save();
            $this->audit->record(new AuditEntry(AuditEvent::UserReactivated, $target, ['status' => UserStatus::Suspended->value], ['status' => UserStatus::Active->value]));
        });

        $target->notify(new AccountStatusChangedNotification(UserStatus::Active));

        return $target;
    }
}
