<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\SuperAdminRevocation;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Console\SuperAdminCommandRejected;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\SuperAdminEligibility;
use App\Services\Identity\SuperAdminFlagWriter;
use App\Services\Notifications\SecurityAlerter;
use App\Support\ValueObjects\EmailAddress;

final readonly class RevokeSuperAdmin
{
    public function __construct(
        private SuperAdminEligibility $eligibility,
        private SuperAdminFlagWriter $writer,
        private AuditRecorder $audit,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(EmailAddress $email, SuperAdminRevocation $revocation, string $operator): User
    {
        $user = User::query()->withEmail($email)->first() ?? throw SuperAdminCommandRejected::userNotFound();

        $this->eligibility->assertRevocable($user, $revocation);
        $this->writer->revoke($user);
        $this->audit->record(new AuditEntry(AuditEvent::SuperAdminRevoked, $user, [], ['os_user' => $operator], ['security']));
        $this->alerter->raise(NotificationCode::SuperAdminGrantChanged);

        return $user;
    }
}
