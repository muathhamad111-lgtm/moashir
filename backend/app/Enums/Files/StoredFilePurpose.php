<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum StoredFilePurpose: string
{
    case RefundEvidence = 'refund_evidence';
    case ApplePayDomainAssociation = 'apple_pay_domain_association';
    case ReceiptPdf = 'receipt_pdf';
    case FinanceExport = 'finance_export';
    case AssessmentEvidence = 'assessment_evidence';
    case EvidenceBundle = 'evidence_bundle';

    private const array UPLOAD_TYPES = [
        'refund_evidence' => ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']],
        'apple_pay_domain_association' => ['' => ['text/plain'], 'txt' => ['text/plain']],
    ];

    /**
     * @return array<string, list<string>>
     */
    public function uploadTypes(): array
    {
        return self::UPLOAD_TYPES[$this->value] ?? [];
    }

    public function directory(): string
    {
        return 'files/'.str_replace('_', '-', $this->value);
    }

    public function visibility(): FileVisibility
    {
        return $this === self::ApplePayDomainAssociation ? FileVisibility::PublicAsset : FileVisibility::Private;
    }

    public function contentClass(): string
    {
        return $this === self::ApplePayDomainAssociation ? 'P' : 'C';
    }

    public function isConfidential(): bool
    {
        return $this->contentClass() === 'C';
    }

    public function isOwnerBound(): bool
    {
        return $this !== self::ApplePayDomainAssociation;
    }

    /**
     * @return list<string>
     */
    public static function ownerBoundValues(): array
    {
        return array_values(array_map(
            static fn (self $purpose): string => $purpose->value,
            array_filter(self::cases(), static fn (self $purpose): bool => $purpose->isOwnerBound()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
