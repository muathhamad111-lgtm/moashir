<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileCompletionItem: string
{
    case BasicData = 'basic_data';
    case AccountOwner = 'account_owner';
    case GovernanceGrade = 'governance_grade';
    case EmployeesCount = 'employees_count';
    case AnnualBeneficiariesCount = 'annual_beneficiaries_count';
    case UnifiedNumber = 'unified_number';
    case LicenseNumber = 'license_number';
    case FoundedOn = 'founded_on';
    case Phone = 'phone';
    case WebsiteUrl = 'website_url';
    case Logo = 'logo';

    public function isRequired(): bool
    {
        return ! in_array($this, [self::Phone, self::WebsiteUrl, self::Logo], true);
    }

    /**
     * @return list<self>
     */
    public static function required(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $item): bool => $item->isRequired()));
    }
}
