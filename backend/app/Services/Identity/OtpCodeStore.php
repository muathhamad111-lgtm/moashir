<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\OtpChannel;
use App\Models\Identity\OtpCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OtpCodeStore
{
    public function __construct(private AuthSettings $settings, private SecretHasher $hasher, private Request $request) {}

    public function open(OtpDelivery $delivery, OtpChannel $channel, string $code, string $maskedDestination): OtpCode
    {
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($delivery, $channel, $code, $maskedDestination, $now): OtpCode {
            OtpCode::query()
                ->where('user_id', $delivery->user->id)
                ->where('purpose', $delivery->purpose)
                ->whereNull('consumed_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => $now]);

            return OtpCode::query()->create([
                'challenge_id' => (string) Str::uuid(),
                'user_id' => $delivery->user->id,
                'channel' => $channel,
                'purpose' => $delivery->purpose,
                'code_hash' => $this->hasher->hash($code),
                'destination_masked' => $maskedDestination,
                'expires_at' => $now->addSeconds($this->settings->otpExpirySeconds()),
                'last_sent_at' => $now,
                'ip_address' => $this->request->ip(),
            ]);
        });
    }
}
