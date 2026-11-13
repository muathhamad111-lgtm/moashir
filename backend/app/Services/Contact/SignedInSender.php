<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Models\Identity\User;
use App\Services\Identity\SessionKeys;
use App\Services\Identity\SessionTimeouts;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

final readonly class SignedInSender
{
    public function __construct(private SessionTimeouts $timeouts) {}

    public function of(Request $request): ?User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession() || ! $this->satisfiesMfa($user)) {
            return null;
        }

        $session = $request->session();
        $signedInAt = ScalarValue::int($session->get(SessionKeys::SIGNED_IN_AT));
        $lastSeenAt = ScalarValue::int($session->get(SessionKeys::LAST_SEEN_AT));

        return $this->timeouts->hasExpired($user, $signedInAt, $lastSeenAt, Date::now()) ? null : $user;
    }

    private function satisfiesMfa(User $user): bool
    {
        return ! $user->requiresMfa() || $user->hasConfirmedTotp();
    }
}
