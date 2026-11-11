<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;
use App\Models\Entities\Entity;
use App\Models\Identity\User;

final readonly class ProfileFieldValues
{
    private function __construct(private Entity $entity, private ?User $owner) {}

    public static function of(Entity $entity): self
    {
        return new self($entity, $entity->loadMissing('owner')->owner);
    }

    public function valueOf(ProfileSectionField $field): string|int|bool|null
    {
        return match ($field) {
            ProfileSectionField::NameAr => $this->entity->name->ar,
            ProfileSectionField::NameEn => $this->entity->name->en,
            ProfileSectionField::UnifiedNumber => $this->entity->unified_number,
            ProfileSectionField::LicenseNumber => $this->entity->license_number,
            ProfileSectionField::FoundedOn => $this->entity->founded_on?->toDateString(),
            ProfileSectionField::EntityType => $this->entity->entity_type->value,
            ProfileSectionField::DevelopmentSector => $this->entity->development_sector->value,
            ProfileSectionField::OrgSize => $this->entity->org_size->value,
            ProfileSectionField::Region => $this->entity->region_id,
            ProfileSectionField::City => $this->entity->city_id,
            ProfileSectionField::GovernanceGrade => $this->entity->governance_grade,
            ProfileSectionField::EmployeesCount => $this->entity->employees_count,
            ProfileSectionField::AnnualBeneficiariesCount => $this->entity->annual_beneficiaries_count,
            ProfileSectionField::HasStrategicPlan => $this->entity->has_strategic_plan,
            ProfileSectionField::OfficialEmail => $this->owner?->email,
            ProfileSectionField::AuthorizedPersonName => $this->owner?->name,
            ProfileSectionField::Phone => $this->owner?->phone,
            ProfileSectionField::WebsiteUrl => $this->entity->website_url,
            ProfileSectionField::MarketingConsent => $this->entity->marketing_consent_at !== null,
        };
    }

    public function isFilled(ProfileSectionField $field): bool
    {
        $value = $this->valueOf($field);

        return is_string($value) ? trim($value) !== '' : $value !== null;
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public function editableValuesOf(ProfileSection $section): array
    {
        $values = [];

        foreach ($section->editableFields() as $field) {
            $values[$field->value] = $this->valueOf($field);
        }

        return $values;
    }
}
