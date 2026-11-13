<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use App\Models\Certificates\Certificate;
use App\Support\Certificates\CertificateLinks;
use App\Support\HijriDate;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Config\Repository;

final readonly class CertificateDocumentBuilder
{
    public function __construct(private Repository $config, private CertificateEntityLogo $entityLogo) {}

    public function for(Certificate $certificate): PdfDocument
    {
        $timezone = $this->config->string('app.display_timezone');
        $issuedAt = $certificate->issued_at->copy()->setTimezone($timezone);
        $snapshot = $certificate->snapshot;

        return new PdfDocument(DocumentTemplate::AccreditedCertificate->value, [
            'number' => $certificate->number,
            'verification_code' => VerificationCode::display($certificate->verification_code),
            'verification_url' => CertificateLinks::verification($certificate->verification_code),
            'request_number' => ScalarValue::string($snapshot['request_number'] ?? null),
            'entity_name' => $snapshot['entity_name'] ?? null,
            'entity_logo' => $this->entityLogo->dataUriOf($certificate),
            'percent' => ScalarValue::string($snapshot['percent'] ?? null),
            'level' => $certificate->level?->value,
            'level_label' => $certificate->level === null ? null : LocalizedText::fromTranslation('certificates.levels.'.$certificate->level->value)->toArray(),
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_hijri' => ['ar' => HijriDate::format($issuedAt, 'ar', $timezone), 'en' => HijriDate::format($issuedAt, 'en', $timezone)],
            'issue_year' => (string) $issuedAt->year,
            'is_test' => $certificate->is_test,
        ]);
    }

    public function fileNameOf(Certificate $certificate): string
    {
        return $certificate->number.'.pdf';
    }
}
