<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileChangeSet;
use App\Enums\Entities\EntityProfileField;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\PhoneNumber;
use BackedEnum;
use Carbon\CarbonInterface;

final class EntityProfileDiff
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function between(Entity $entity, array $attributes): EntityProfileChangeSet
    {
        $changes = new EntityProfileChangeSet;

        foreach ($attributes as $field => $value) {
            $changes = $changes->merge($this->change($field, $this->comparable($entity->getAttribute($field)), $this->comparable($value)));
        }

        return $changes;
    }

    public function ofContact(?User $owner, ?EntityContactData $contact): EntityProfileChangeSet
    {
        if (! $owner instanceof User || ! $contact instanceof EntityContactData) {
            return new EntityProfileChangeSet;
        }

        return $this->change(EntityProfileField::AuthorizedPersonName->value, $owner->name, $contact->authorizedPersonName)
            ->merge($this->phoneChange($owner->phone, $contact->phone));
    }

    public function ofConsent(Entity $entity, ?bool $consents): EntityProfileChangeSet
    {
        return $consents === null
            ? new EntityProfileChangeSet
            : $this->change(EntityProfileField::MarketingConsent->value, $entity->marketing_consent_at !== null, $consents);
    }

    private function change(string $field, mixed $previous, mixed $next): EntityProfileChangeSet
    {
        return $previous === $next ? new EntityProfileChangeSet : new EntityProfileChangeSet([$field => $previous], [$field => $next]);
    }

    private function phoneChange(?string $previous, ?PhoneNumber $next): EntityProfileChangeSet
    {
        if ($previous === $next?->value) {
            return new EntityProfileChangeSet;
        }

        $field = EntityProfileField::Phone->value;
        $masked = $previous === null ? null : PhoneNumber::tryFromSaudiInput($previous)?->masked();

        return new EntityProfileChangeSet([$field => $masked], [$field => $next?->masked()]);
    }

    private function comparable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof EntityName => $value->toArray(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toDateString(),
            default => $value,
        };
    }
}
