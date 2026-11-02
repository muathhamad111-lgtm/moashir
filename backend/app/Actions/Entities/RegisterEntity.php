<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Actions\Identity\IssueOtpChallenge;
use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityRegistrationData;
use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityAccountCreator;
use App\Services\Identity\DecoyOtpChallenges;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class RegisterEntity
{
    public function __construct(
        private EntityAccountCreator $creator,
        private IssueOtpChallenge $issueChallenge,
        private DecoyOtpChallenges $decoys,
        private AuditRecorder $audit,
        private Hasher $hasher,
    ) {}

    public function handle(EntityRegistrationData $data): OtpChallenge
    {
        if (User::query()->withTrashed()->withEmail($data->officialEmail)->exists()) {
            return $this->decoy($data);
        }

        try {
            $entity = DB::transaction(fn (): Entity => $this->register($data));
        } catch (UniqueConstraintViolationException) {
            return $this->decoy($data);
        }

        $owner = $entity->owner()->firstOrFail();

        return $this->issueChallenge->handle(new OtpDelivery($owner, OtpChannel::Email, OtpPurpose::Login));
    }

    private function register(EntityRegistrationData $data): Entity
    {
        $entity = $this->creator->create($data);

        $this->audit->record(new AuditEntry(AuditEvent::EntityRegistered, $entity, [], [
            'status' => $entity->status->value,
            'entity_type' => $entity->entity_type->value,
            'region_id' => $entity->region_id,
            'marketing_consent' => $data->marketingConsent,
            'terms_version_ids' => $data->acceptedTermsVersionIds,
        ], ['registration']));

        return $entity;
    }

    private function decoy(EntityRegistrationData $data): OtpChallenge
    {
        $this->hasher->make($data->password);

        return $this->decoys->issue(OtpChannel::Email);
    }
}
