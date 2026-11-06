<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\SignInCodeTarget;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\SignInCodeVerdict;
use App\Models\Identity\User;

final readonly class SignInCodeDecoy
{
    public function __construct(private DecoyOtpChallenges $decoys, private AuthenticationEventRecorder $events) {}

    public function answer(SignInCodeTarget $target, SignInCodeVerdict $verdict): OtpChallenge
    {
        $this->recordIgnored($target, $verdict);

        return $this->decoys->issue($target->channel);
    }

    private function recordIgnored(SignInCodeTarget $target, SignInCodeVerdict $verdict): void
    {
        if (! $target->account instanceof User) {
            $this->events->forUnknownIdentifier(AuthEventType::OtpRequestIgnored, $target->identifier, ['reason' => $verdict->value]);

            return;
        }

        $this->events->forUser(AuthEventType::OtpRequestIgnored, $target->account, [
            'reason' => $verdict->value,
            'identifier_type' => $target->identifier->type->value,
        ]);
    }
}
