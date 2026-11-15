<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EmailVerificationToken;
use App\Exceptions\Entities\RegistrationRejected;
use App\Models\Identity\RegistrationEmailVerification;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\SecretHasher;
use App\Support\ValueObjects\EmailAddress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class RegistrationEmailTokens
{
    private const int TOKEN_LENGTH = 64;

    public function __construct(
        private AuthSettings $settings,
        private SecretHasher $hasher,
        private RegistrationEmailIndex $index,
    ) {}

    public function issue(RegistrationEmailVerification $verification): EmailVerificationToken
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $expiresAt = CarbonImmutable::now()->addMinutes($this->settings->registrationEmailTokenMinutes());

        $verification->forceFill(['token_hash' => $this->hasher->hash($token), 'token_expires_at' => $expiresAt])->save();

        return new EmailVerificationToken($token, $expiresAt);
    }

    /**
     * Uses up the token of a verified address. Call it inside the registration transaction so a failed
     * registration keeps the token usable.
     */
    public function consume(?string $token, EmailAddress $email): void
    {
        $verification = $token === null || $token === '' ? null : RegistrationEmailVerification::query()
            ->where('token_hash', $this->hasher->hash($token))
            ->lockForUpdate()
            ->first();

        if (! $verification instanceof RegistrationEmailVerification
            || ! $verification->hasUsableToken()
            || ! hash_equals($verification->email_index, $this->index->of($email))) {
            throw RegistrationRejected::emailUnverified();
        }

        $verification->forceFill(['token_used_at' => Date::now()])->save();
    }
}
