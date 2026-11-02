<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Support\ValueObjects\EntityName;

final readonly class EntityProfileData
{
    public function __construct(
        public EntityName $name,
        public EntityType $entityType,
        public DevelopmentSector $developmentSector,
        public OrgSize $orgSize,
        public int $foundingYear,
        public int $regionId,
        public int $cityId,
        public ?string $governanceGrade,
        public ?int $employeesCount,
        public ?int $annualBeneficiariesCount,
        public bool $hasStrategicPlan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            EntityProfileField::Name->value => $this->name,
            EntityProfileField::EntityType->value => $this->entityType,
            EntityProfileField::DevelopmentSector->value => $this->developmentSector,
            EntityProfileField::OrgSize->value => $this->orgSize,
            EntityProfileField::FoundingYear->value => $this->foundingYear,
            EntityProfileField::Region->value => $this->regionId,
            EntityProfileField::City->value => $this->cityId,
            EntityProfileField::GovernanceGrade->value => $this->governanceGrade,
            EntityProfileField::EmployeesCount->value => $this->employeesCount,
            EntityProfileField::AnnualBeneficiariesCount->value => $this->annualBeneficiariesCount,
            EntityProfileField::HasStrategicPlan->value => $this->hasStrategicPlan,
        ];
    }

    public function isComplete(): bool
    {
        return $this->governanceGrade !== null && $this->employeesCount !== null && $this->annualBeneficiariesCount !== null;
    }
}
