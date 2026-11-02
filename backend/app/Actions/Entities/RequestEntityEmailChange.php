<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Actions\Identity\IssueOtpChallenge;
use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Entities\EmailChangeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\OtpCode;
use App\Models\Identity\User;
use App\Services\Entities\EntitySettings;
use App\Services\Entities\OwnEntity;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\DB;

final readonly class RequestEntityEmailChange
{
    public function __construct(
        private OwnEntity $ownEntity,
        private EntitySettings $settings,
        private IssueOtpChallenge $issueChallenge,
    ) {}

    public function handle(User $owner, EmailAddress $newEmail): OtpChallenge
    {
        $entity = $this->ownEntity->of($owner);

        if (! $entity->isActive()) {
            throw IllegalStateTransition::from($entity->status->value, 'change_email');
        }

        return DB::transaction(function () use ($owner, $entity, $newEmail): OtpChallenge {
            $open = EmailChangeRequest::query()
                ->where('user_id', $owner->id)
                ->whereIn('status', [EmailChangeStatus::AwaitingOtp, EmailChangeStatus::AwaitingApproval])
                ->lockForUpdate()
                ->get();

            if ($open->contains(static fn (EmailChangeRequest $request): bool => $request->isAwaitingApproval())) {
                throw EmailChangeRejected::pendingApproval();
            }

            EmailChangeRequest::query()->whereKey($open->modelKeys())->update(['status' => EmailChangeStatus::Cancelled]);

            $challenge = $this->issueChallenge->handle(new OtpDelivery($owner, OtpChannel::Email, OtpPurpose::EmailChange, $newEmail));

            EmailChangeRequest::query()->create([
                'user_id' => $owner->id,
                'entity_id' => $entity->id,
                'old_email' => $owner->email,
                'new_email' => $newEmail->value,
                'otp_code_id' => OtpCode::query()->where('challenge_id', $challenge->challengeId)->value('id'),
                'policy' => $this->settings->emailChangePolicy(),
                'status' => EmailChangeStatus::AwaitingOtp,
            ]);

            return $challenge;
        });
    }
}
