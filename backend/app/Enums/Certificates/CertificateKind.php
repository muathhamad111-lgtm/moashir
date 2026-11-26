<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

enum CertificateKind: string
{
    case Accredited = 'accredited';
    case AdvancedWinner = 'advanced_winner';
    case AdvancedQualified = 'advanced_qualified';
    case AdvancedParticipation = 'advanced_participation';

    /**
     * @return list<self>
     */
    public static function award(): array
    {
        return [self::AdvancedWinner, self::AdvancedQualified, self::AdvancedParticipation];
    }

    public function isAward(): bool
    {
        return $this !== self::Accredited;
    }
}
