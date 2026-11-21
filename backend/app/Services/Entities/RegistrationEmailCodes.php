<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Models\Identity\RegistrationEmailVerification;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\SecretHasher;
use App\Support\ValueObjects\EmailAddress;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RegistrationEmailCodes
{
    public function __construct(
        private AuthSettings $settings,
        private SecretHasher $hasher,
        private RegistrationEmailIndex $index,
        private Request $request,
    ) {}

    /**
     * Opens a new e-mail check for the address and voids the address's earlier open checks.
     */
    public function open(EmailAddress $email, string $code): RegistrationEmailVerification
    {
        $now = CarbonImmutable::now();
        $emailIndex = $this->index->of($email);

        return DB::transaction(function () use ($email, $emailIndex, $code, $now): RegistrationEmailVerification {
            RegistrationEmailVerification::query()
                ->where('email_index', $emailIndex)
                ->whereNull('consumed_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => $now]);

            return RegistrationEmailVerification::query()->create([
                'challenge_id' => (string) Str::uuid(),
                'email' => $email->value,
                'email_index' => $emailIndex,
                'code_hash' => $this->hasher->hash($code),
                'expires_at' => $now->addSeconds($this->settings->otpExpirySeconds()),
                'last_sent_at' => $now,
                'ip_address' => $this->request->ip(),
            ]);
        });
    }

    public function findOpen(string $challengeId): ?RegistrationEmailVerification
    {
        $verification = RegistrationEmailVerification::query()->where('challenge_id', $challengeId)->first();

        return $verification?->isOpen() === true ? $verification : null;
    }
}
