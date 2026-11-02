<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AuthEventType;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class UnlockUser
{
    public function __construct(
        private RequestContext $context,
        private AuthenticationEventRecorder $events,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $target, ?string $reason): User
    {
        if (! $target->isLocked()) {
            throw IllegalStateTransition::from($target->effectiveStatus()->value, 'unlock');
        }

        $this->context->recordReason($reason);

        DB::transaction(function () use ($target): void {
            $target->forceFill(['locked_until' => null, 'failed_login_attempts' => 0])->save();
            $this->events->forUser(AuthEventType::Unlocked, $target);
            $this->audit->record(new AuditEntry(AuditEvent::UserUnlocked, $target));
        });

        return $target;
    }
}
