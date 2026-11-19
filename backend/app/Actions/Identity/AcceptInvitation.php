<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\LoginOutcome;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\SignInMethod;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\UsableInvitationFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AcceptInvitation
{
    public function __construct(
        private UsableInvitationFinder $invitations,
        private ApplyNewPassword $applyPassword,
        private AuditRecorder $audit,
        private CompleteSignIn $completeSignIn,
        private Request $request,
    ) {}

    public function handle(string $token, string $password, ?TermsVersion $terms): LoginOutcome
    {
        $user = DB::transaction(function () use ($token, $password, $terms): User {
            $invitation = $this->invitations->findForUpdate($token);
            $user = $invitation->user;

            $invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_at' => Date::now()])->save();
            $user->forceFill(['status' => UserStatus::Active, 'email_verified_at' => Date::now()])->save();
            $this->applyPassword->handle($user, $password);
            $this->recordTermsAcceptance($user, $terms);
            $this->audit->record(new AuditEntry(AuditEvent::UserActivated, $user));

            return $user;
        });

        return $this->completeSignIn->handle($user, SignInMethod::Invitation);
    }

    private function recordTermsAcceptance(User $user, ?TermsVersion $terms): void
    {
        if (! $terms instanceof TermsVersion) {
            return;
        }

        $user->termsAcceptances()->create([
            'terms_version_id' => $terms->id,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
