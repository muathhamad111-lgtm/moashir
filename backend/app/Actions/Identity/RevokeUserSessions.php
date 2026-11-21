<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AuthEventType;
use App\Exceptions\Identity\AccountActionRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\UserSessionTerminator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class RevokeUserSessions
{
    public function __construct(
        private RequestContext $context,
        private UserSessionTerminator $sessions,
        private AuthenticationEventRecorder $events,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, User $target, ?string $reason): User
    {
        if ($actor->is($target)) {
            throw AccountActionRejected::selfTarget();
        }

        $this->context->recordReason($reason);

        DB::transaction(function () use ($target): void {
            $ended = $this->sessions->terminateAll($target);
            $this->events->forUser(AuthEventType::SessionRevoked, $target, ['scope' => 'all', 'count' => $ended]);
            $this->audit->record(new AuditEntry(AuditEvent::UserSessionsRevoked, $target, [], ['sessions_ended' => $ended]));
        });

        return $target;
    }
}
