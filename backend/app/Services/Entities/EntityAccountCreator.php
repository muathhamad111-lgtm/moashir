<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Actions\Identity\ApplyNewPassword;
use App\Data\Entities\EntityRegistrationData;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Events\Entities\EntityRegistered;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Identity\TermsAcceptanceRecorder;

final readonly class EntityAccountCreator
{
    public function __construct(
        private ApplyNewPassword $applyPassword,
        private TermsAcceptanceRecorder $terms,
        private MarketingConsentRecorder $consent,
    ) {}

    public function create(EntityRegistrationData $data): Entity
    {
        $entity = new Entity;
        $entity->fill($entity->profileAttributesFrom($data->profile));
        $entity->forceFill(['status' => EntityStatus::PendingVerification])->save();

        $owner = $this->createOwner($entity, $data);
        $entity->setRelation('owner', $owner);
        $entity->syncProfileCompletedAt();
        $entity->save();

        $this->applyPassword->handle($owner, $data->password);
        $this->terms->accept($owner, $data->acceptedTermsVersionIds);
        $this->consent->record($entity, $data->marketingConsent);

        EntityRegistered::dispatch($entity);

        return $entity;
    }

    private function createOwner(Entity $entity, EntityRegistrationData $data): User
    {
        $owner = new User;
        User::withoutAuditing(static fn (): bool => $owner->forceFill([
            'name' => $data->contact->authorizedPersonName,
            'email' => $data->officialEmail->value,
            'phone' => $data->contact->phone?->value,
            'locale' => $data->locale,
            'entity_id' => $entity->id,
            'status' => UserStatus::Active,
            'mfa_required' => false,
        ])->save());
        $owner->assignRole(RoleKey::Entity->value);

        return $owner;
    }
}
