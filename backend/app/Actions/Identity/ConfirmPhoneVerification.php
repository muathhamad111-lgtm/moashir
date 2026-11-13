<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Identity\OtpRejected;
use App\Exceptions\Identity\PhoneVerificationRejected;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmPhoneVerification
{
    public function __construct(private VerifyOtpCode $verifyOtp) {}

    public function handle(User $user, string $challengeId, string $code): User
    {
        $this->assertChallengeOf($user, $challengeId);
        $this->verifyOtp->handle($challengeId, $code, OtpPurpose::VerifyPhone);
        $this->assertNumberIsFree($user);

        try {
            DB::transaction(static fn (): bool => $user->forceFill(['phone_verified_at' => Date::now()])->save());
        } catch (UniqueConstraintViolationException) {
            throw PhoneVerificationRejected::inUse();
        }

        return $user;
    }

    private function assertChallengeOf(User $user, string $challengeId): void
    {
        $challenge = OtpCode::query()
            ->where('challenge_id', $challengeId)
            ->where('purpose', OtpPurpose::VerifyPhone)
            ->where('user_id', $user->id)
            ->where('channel', OtpChannel::Sms)
            ->first();

        if (! $challenge instanceof OtpCode || $user->phone === null || $challenge->destination_masked !== new PhoneNumber($user->phone)->masked()) {
            throw OtpRejected::challengeInvalid();
        }
    }

    private function assertNumberIsFree(User $user): void
    {
        $isTaken = User::query()
            ->whereKeyNot($user->id)
            ->where('phone_index', $user->phone_index)
            ->whereNotNull('phone_verified_at')
            ->exists();

        if ($isTaken) {
            throw PhoneVerificationRejected::inUse();
        }
    }
}
