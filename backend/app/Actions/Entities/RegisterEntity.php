<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Actions\Identity\CompleteSignIn;
use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityRegistrationData;
use App\Data\Entities\RegistrationDocuments;
use App\Data\Identity\LoginOutcome;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\SignInMethod;
use App\Exceptions\Entities\RegistrationRejected;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityAccountCreator;
use App\Services\Entities\EntityRegistrationDocuments;
use App\Services\Entities\RegistrationEmailTokens;
use App\Support\Entities\EntityUniqueViolation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registers an entity whose official e-mail was already proven on the registration form, with its license
 * certificate and authorization letter, then confirms it exactly like a verified registration: held for staff
 * review, or activated and signed in. The documents are scanned before the transaction and removed if it fails.
 */
final readonly class RegisterEntity
{
    public function __construct(
        private RegistrationEmailTokens $emailTokens,
        private EntityAccountCreator $creator,
        private VerifyEntityRegistration $verifyRegistration,
        private AuditRecorder $audit,
        private CompleteSignIn $completeSignIn,
        private EntityRegistrationDocuments $documents,
    ) {}

    public function handle(EntityRegistrationData $data, RegistrationDocuments $documents, ?string $emailVerificationToken): LoginOutcome
    {
        $accepted = $this->documents->accept($documents);

        try {
            $owner = $this->registerWith($data, $accepted, $emailVerificationToken);
        } catch (Throwable $failure) {
            $this->documents->discard($accepted);

            throw $failure;
        }

        return $this->outcomeFor($owner->refresh());
    }

    /**
     * @param  array<string, StoredFile>  $accepted
     */
    private function registerWith(EntityRegistrationData $data, array $accepted, ?string $emailVerificationToken): User
    {
        try {
            return DB::transaction(function () use ($data, $accepted, $emailVerificationToken): User {
                $this->emailTokens->consume($emailVerificationToken, $data->officialEmail);
                $this->assertAddressFree($data);

                $entity = $this->register($data);
                $owner = $entity->owner()->firstOrFail();
                $this->documents->attachTo($entity, $owner, $accepted);
                $this->verifyRegistration->handle($owner);

                return $owner;
            });
        } catch (UniqueConstraintViolationException $violation) {
            $this->rethrowIfEntityField($violation);

            throw RegistrationRejected::emailTaken();
        }
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
