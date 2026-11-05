<?php

declare(strict_types=1);

namespace App\Enums\Contact;

enum ContactEnquiryType: string
{
    case General = 'general';
    case SelfAssessment = 'self_assessment';
    case AccreditedAssessment = 'accredited_assessment';
    case Payments = 'payments';
    case TechnicalSupport = 'technical_support';
    case Other = 'other';
    case PrivacyRequest = 'privacy_request';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
