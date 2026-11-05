<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Notifications\Identity\SuperAdminEmailChangedNotification;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\UserSessionTerminator;
use App\Services\Notifications\SecurityAlerter;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class ConfirmSuperAdminEmailChange
{
    public function __construct(
        private VerifyOtpCode $verifyOtp,
        private AuditRecorder $audit,
        private SecurityAlerter $alerter,
        private UserSessionTerminator $sessions,
        private SignOut $signOut,
    ) {}

    public function handle(User $user, string $challengeId, string $code): void
    {
        $otp = $this->verifyOtp->handle($challengeId, $code, OtpPurpose::EmailChange);
        $request = EmailChangeRequest::query()
            ->where('otp_code_id', $otp->id)
            ->where('user_id', $user->id)
            ->where('status', EmailChangeStatus::AwaitingOtp)
            ->first() ?? throw OtpRejected::challengeInvalid();

        $oldEmail = new EmailAddress($request->old_email);
        $newEmail = new EmailAddress($request->new_email);

        DB::transaction(function () use ($user, $request, $oldEmail, $newEmail): void {
            $user->forceFill(['email' => $newEmail->value, 'password_change_required' => true])->save();
            $request->forceFill(['status' => EmailChangeStatus::Applied, 'otp_verified_at' => Date::now(), 'applied_at' => Date::now()])->save();
            $this->audit->record(new AuditEntry(
                AuditEvent::SuperAdminEmailChanged,
                $user,
                ['email' => $oldEmail->masked()],
                ['email' => $newEmail->masked()],
                ['security'],
            ));
        });

        $this->notifyAddresses($user, $oldEmail, $newEmail);
        $this->signOut->handle($user);
        $this->sessions->terminateAll($user);
    }

    private function notifyAddresses(User $user, EmailAddress $oldEmail, EmailAddress $newEmail): void
    {
        $notification = new SuperAdminEmailChangedNotification($newEmail->masked())->locale($user->preferredLocale());

        Notification::route('mail', [$oldEmail->value, $newEmail->value])->notify($notification);
        $this->alerter->raise(NotificationCode::SuperAdminEmailChanged);
    }
}
