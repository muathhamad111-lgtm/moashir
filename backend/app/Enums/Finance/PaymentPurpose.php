<?php

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\PermissionKey;

enum PaymentPurpose: string
{
    case Accredited = 'accredited';
    case Bag = 'bag';
    case SelfAssessment = 'self';
    case Award = 'award';

    public function requiredPermission(): PermissionKey
    {
        return match ($this) {
            self::SelfAssessment => PermissionKey::AssessmentSelfManage,
            self::Accredited => PermissionKey::AssessmentAccreditedManage,
            self::Award => PermissionKey::AssessmentAdvancedManage,
            self::Bag => PermissionKey::BagOwnPurchase,
        };
    }

    public function opensPath(): ?AssessmentPath
    {
        return match ($this) {
            self::SelfAssessment => AssessmentPath::SelfAssessment,
            self::Accredited => AssessmentPath::Accredited,
            self::Award => AssessmentPath::Advanced,
            self::Bag => null,
        };
    }

    public function tracksCheckoutIntent(): bool
    {
        return $this === self::Accredited || $this === self::Bag;
    }

    public function isFreeUntilPriced(): bool
    {
        return $this === self::SelfAssessment || $this === self::Award;
    }

    public function isPerCycle(): bool
    {
        return $this === self::Award;
    }

    public static function ofPath(AssessmentPath $path): self
    {
        return match ($path) {
            AssessmentPath::SelfAssessment => self::SelfAssessment,
            AssessmentPath::Accredited => self::Accredited,
            AssessmentPath::Advanced => self::Award,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function pathPurposes(): array
    {
        return [self::Accredited, self::SelfAssessment, self::Award];
    }
}
