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
    case CmsImage = 'cms_image';
    case KpiImage = 'kpi_image';
    case BrandingLogo = 'branding_logo';
    case CertificatePdf = 'certificate_pdf';
    case EntityLogo = 'entity_logo';

    private const array PUBLIC_ASSETS = ['apple_pay_domain_association', 'cms_image', 'kpi_image', 'branding_logo'];

    private const array SERVED_IMAGES = ['cms_image', 'kpi_image', 'branding_logo'];

    private const array UPLOAD_TYPES = [
        'refund_evidence' => ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']],
        'apple_pay_domain_association' => ['' => ['text/plain'], 'txt' => ['text/plain']],
        'cms_image' => ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']],
        'kpi_image' => ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']],
        'branding_logo' => ['png' => ['image/png']],
        'entity_logo' => ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']],
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
        return $this->isPublicAsset() ? FileVisibility::PublicAsset : FileVisibility::Private;
    }

    public function contentClass(): string
    {
        return match (true) {
            $this->isPublicAsset() => 'P',
            $this === self::EntityLogo => 'I',
            default => 'C',
        };
    }

    public function isPublicAsset(): bool
    {
        return in_array($this->value, self::PUBLIC_ASSETS, true);
    }

    /**
     * @return list<string>
     */
    public static function servedImageValues(): array
    {
        return self::SERVED_IMAGES;
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
