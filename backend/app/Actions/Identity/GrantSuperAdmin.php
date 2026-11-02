<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\SuperAdminEligibility;
use App\Services\Identity\SuperAdminFlagWriter;
use App\Services\Notifications\SecurityAlerter;
use App\Support\ValueObjects\EmailAddress;

final readonly class GrantSuperAdmin
{
    public function __construct(
        private SuperAdminEligibility $eligibility,
        private SuperAdminFlagWriter $writer,
        private AuditRecorder $audit,
        private SecurityAlerter $alerter,
        private RequestPasswordReset $requestPasswordReset,
    ) {}

    public function handle(EmailAddress $email, ?string $name, string $operator): User
    {
        $user = $this->eligibility->candidateFor($email, $name);

        $this->writer->grant($user);
        $this->audit->record(new AuditEntry(AuditEvent::SuperAdminGranted, $user, [], ['os_user' => $operator], ['security']));
        $this->alerter->raise(NotificationCode::SuperAdminGrantChanged);

        if ($user->password === null) {
            $this->requestPasswordReset->handle($email);
        }

        return $user;
    }
}
