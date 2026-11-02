<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\PendingAuthenticationStage;
use App\Models\Identity\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

final readonly class MfaSubject
{
    public function __construct(private AuthFactory $auth, private PendingAuthentication $pending) {}

    public function resolve(): User
    {
        $user = $this->auth->guard('web')->user();

        return $user instanceof User ? $user : $this->pending->userFor(PendingAuthenticationStage::MfaEnrollment);
    }

    public function isPendingEnrollment(): bool
    {
        return $this->auth->guard('web')->guest() && $this->pending->isPending(PendingAuthenticationStage::MfaEnrollment);
    }
}
