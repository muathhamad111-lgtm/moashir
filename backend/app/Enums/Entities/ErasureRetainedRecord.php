<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ErasureRetainedRecord: string
{
    case Payments = 'payments_receipts_refunds';
    case AuditLog = 'audit_log';
    case AnonymizedResults = 'anonymized_results';
    case CertificateVerification = 'certificate_verification_records';
    case ConsentRecords = 'consent_records';
    case SecurityEvents = 'security_events';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
