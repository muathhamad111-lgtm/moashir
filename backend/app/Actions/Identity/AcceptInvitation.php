<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\InvitationAcceptance;
use App\Data\Identity\LoginOutcome;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\SignInMethod;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\InviteeNationalId;
use App\Services\Identity\TermsAcceptanceRecorder;
use App\Services\Identity\UsableInvitationFinder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AcceptInvitation
{
    public function __construct(
        private UsableInvitationFinder $invitations,
        private ApplyNewPassword $applyPassword,
        private AuditRecorder $audit,
        private CompleteSignIn $completeSignIn,
        private TermsAcceptanceRecorder $terms,
        private InviteeNationalId $nationalId,
    ) {}

    public function handle(string $token, InvitationAcceptance $acceptance): LoginOutcome
    {
        $user = DB::transaction(function () use ($token, $acceptance): User {
            $invitation = $this->invitations->findForUpdate($token);
            $user = $invitation->user;

            $this->nationalId->record($user, $acceptance->nationalId);
            $invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_at' => Date::now()])->save();
            $user->forceFill(['status' => UserStatus::Active, 'email_verified_at' => Date::now()])->save();
            $this->applyPassword->handle($user, $acceptance->password);
            $this->terms->accept($user, $acceptance->terms instanceof TermsVersion ? [$acceptance->terms->id] : []);

            if ($acceptance->nda instanceof TermsVersion) {
                $this->terms->acceptNda($user, $acceptance->nda);
            }

            $this->audit->record(new AuditEntry(AuditEvent::UserActivated, $user));

            return $user;
        });

        return $this->completeSignIn->handle($user, SignInMethod::Invitation);
    }
}
