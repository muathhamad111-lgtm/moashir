<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum CriterionGroup: string
{
    case Enablers = 'enablers';
    case CreativityLearning = 'creativity_learning';
    case Results = 'results';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
