<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\MfaConfirmation;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\SignInMethod;
use App\Exceptions\Identity\MfaRejected;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\MfaSubject;
use App\Support\StringList;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

final readonly class ConfirmMfaEnrollment
{
    public function __construct(
        private MfaSubject $subject,
        private ConfirmTwoFactorAuthentication $confirm,
        private AuthenticationEventRecorder $events,
        private EstablishSession $establishSession,
    ) {}

    public function handle(string $code): MfaConfirmation
    {
        $isPending = $this->subject->isPendingEnrollment();
        $user = $this->subject->resolve();

        if ($user->hasConfirmedTotp()) {
            throw MfaRejected::alreadyEnrolled();
        }

        try {
            ($this->confirm)($user, $code);
        } catch (ValidationException) {
            throw MfaRejected::invalidCode();
        }

        $this->events->forUser(AuthEventType::MfaEnrolled, $user);
        $recoveryCodes = StringList::from($user->recoveryCodes());

        return new MfaConfirmation($recoveryCodes, $isPending ? $this->establishSession->handle($user, SignInMethod::TotpEnrollment) : null);
    }
}
