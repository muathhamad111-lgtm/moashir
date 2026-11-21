<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Actions\Identity\CompleteSignIn;
use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityRegistrationData;
use App\Data\Identity\LoginOutcome;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\SignInMethod;
use App\Exceptions\Entities\RegistrationRejected;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityAccountCreator;
use App\Services\Entities\RegistrationEmailTokens;
use App\Support\Entities\EntityUniqueViolation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers an entity whose official e-mail was already proven on the registration form, then
 * confirms it exactly like a verified registration: held for staff review, or activated and signed in.
 */
final readonly class RegisterEntity
{
    public function __construct(
        private RegistrationEmailTokens $emailTokens,
        private EntityAccountCreator $creator,
        private VerifyEntityRegistration $verifyRegistration,
        private AuditRecorder $audit,
        private CompleteSignIn $completeSignIn,
    ) {}

    public function handle(EntityRegistrationData $data, ?string $emailVerificationToken): LoginOutcome
    {
        try {
            $owner = DB::transaction(function () use ($data, $emailVerificationToken): User {
                $this->emailTokens->consume($emailVerificationToken, $data->officialEmail);
                $this->assertAddressFree($data);

                $owner = $this->register($data)->owner()->firstOrFail();
                $this->verifyRegistration->handle($owner);

                return $owner;
            });
        } catch (UniqueConstraintViolationException $violation) {
            $this->rethrowIfEntityField($violation);

            throw RegistrationRejected::emailTaken();
        }

        return $this->outcomeFor($owner->refresh());
    }

    private function assertAddressFree(EntityRegistrationData $data): void
    {
        if (User::query()->withTrashed()->withEmail($data->officialEmail)->exists()) {
            throw RegistrationRejected::emailTaken();
        }
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

    private function outcomeFor(User $owner): LoginOutcome
    {
        $entity = Entity::query()->findOrFail($owner->entity_id);

        if (! $entity->isActive()) {
            return LoginOutcome::pendingReview();
        }

        return $this->completeSignIn->handle($owner, SignInMethod::Registration);
    }

    private function rethrowIfEntityField(UniqueConstraintViolationException $violation): void
    {
        $validation = EntityUniqueViolation::toValidation($violation);

        if ($validation instanceof ValidationException) {
            throw $validation;
        }
    }
}
