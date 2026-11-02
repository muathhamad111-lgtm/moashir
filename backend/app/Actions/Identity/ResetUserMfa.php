<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Identity\AccountActionRejected;
use App\Models\Identity\User;
use App\Notifications\Identity\MfaResetNotification;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\UserSessionTerminator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class ResetUserMfa
{
    public function __construct(
        private RequestContext $context,
        private UserSessionTerminator $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, User $target, string $reason): User
    {
        if ($actor->is($target)) {
            throw AccountActionRejected::selfTarget();
        }

        $this->context->recordReason($reason);

        DB::transaction(function () use ($target): void {
            $target->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
            $this->sessions->terminateAll($target);
            $this->audit->record(new AuditEntry(AuditEvent::UserMfaReset, $target));
        });

        $target->notify(new MfaResetNotification);

        return $target;
    }
}
