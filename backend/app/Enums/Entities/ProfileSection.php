<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileSection: string
{
    case Identity = 'identity';
    case Classification = 'classification';
    case Governance = 'governance';
    case Contact = 'contact';

    /**
     * @return list<ProfileSectionField>
     */
    public function fields(): array
    {
        return array_values(array_filter(ProfileSectionField::cases(), fn (ProfileSectionField $field): bool => $field->section() === $this));
    }

    /**
     * @return list<ProfileSectionField>
     */
    public function editableFields(): array
    {
        return array_values(array_filter($this->fields(), static fn (ProfileSectionField $field): bool => $field->isEditable()));
    }

    /**
     * @return list<EntityProfileField>
     */
    public function historyFields(): array
    {
        return array_values(array_filter(EntityProfileField::cases(), fn (EntityProfileField $field): bool => $field->section() === $this));
    }
}
