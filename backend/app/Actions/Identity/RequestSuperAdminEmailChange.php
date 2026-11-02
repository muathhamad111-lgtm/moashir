<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\DB;

final readonly class RequestSuperAdminEmailChange
{
    public function __construct(private IssueOtpChallenge $issueChallenge) {}

    public function handle(User $user, EmailAddress $newEmail): OtpChallenge
    {
        return DB::transaction(function () use ($user, $newEmail): OtpChallenge {
            EmailChangeRequest::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [EmailChangeStatus::AwaitingOtp, EmailChangeStatus::AwaitingApproval])
                ->update(['status' => EmailChangeStatus::Cancelled]);

            $challenge = $this->issueChallenge->handle(new OtpDelivery($user, OtpChannel::Email, OtpPurpose::EmailChange, $newEmail));

            EmailChangeRequest::query()->create([
                'user_id' => $user->id,
                'old_email' => $user->email,
                'new_email' => $newEmail->value,
                'otp_code_id' => OtpCode::query()->where('challenge_id', $challenge->challengeId)->value('id'),
                'policy' => EmailChangePolicy::ImmediateNotify,
                'status' => EmailChangeStatus::AwaitingOtp,
            ]);

            return $challenge;
        });
    }
}
