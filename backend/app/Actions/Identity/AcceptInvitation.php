<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\LoginOutcome;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\SignInMethod;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Identity\AccountActionRejected;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\UserInvitation;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\SecretHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AcceptInvitation
{
    public function __construct(
        private SecretHasher $hasher,
        private ApplyNewPassword $applyPassword,
        private AuditRecorder $audit,
        private CompleteSignIn $completeSignIn,
        private Request $request,
    ) {}

    public function handle(string $token, string $password, ?TermsVersion $terms): LoginOutcome
    {
        $invitation = $this->findUsableInvitation($token);
        $user = $invitation->user;

        DB::transaction(function () use ($invitation, $user, $password, $terms): void {
            $invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_at' => Date::now()])->save();
            $user->forceFill(['status' => UserStatus::Active, 'email_verified_at' => Date::now()])->save();
            $this->applyPassword->handle($user, $password);

            if ($terms instanceof TermsVersion) {
                $user->termsAcceptances()->create([
                    'terms_version_id' => $terms->id,
                    'ip_address' => $this->request->ip(),
                    'user_agent' => $this->request->userAgent(),
                ]);
            }

            $this->audit->record(new AuditEntry(AuditEvent::UserActivated, $user));
        });

        return $this->completeSignIn->handle($user, SignInMethod::Invitation);
    }

    private function findUsableInvitation(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()->where('token_hash', $this->hasher->hash($token))->first();

        if ($invitation === null || $invitation->status === InvitationStatus::Accepted || $invitation->status === InvitationStatus::Revoked) {
            throw AccountActionRejected::invitationInvalid();
        }

        if (! $invitation->isUsable()) {
            throw AccountActionRejected::invitationExpired();
        }

        return $invitation;
    }
}
