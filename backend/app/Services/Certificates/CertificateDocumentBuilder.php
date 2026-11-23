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
    public function __construct(private Repository $config) {}

    public function for(Certificate $certificate): PdfDocument
    {
        return $certificate->kind->isAward()
            ? new PdfDocument(DocumentTemplate::AwardCertificate->value, [...$this->commonData($certificate), ...$this->awardData($certificate)])
            : new PdfDocument(DocumentTemplate::AccreditedCertificate->value, [...$this->commonData($certificate), ...$this->accreditedData($certificate)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function commonData(Certificate $certificate): array
    {
        $timezone = $this->config->string('app.display_timezone');
        $issuedAt = $certificate->issued_at->copy()->setTimezone($timezone);
        $snapshot = $certificate->snapshot;

        return [
            'number' => $certificate->number,
            'verification_url' => CertificateLinks::verification($certificate->verification_code),
            'request_number' => ScalarValue::string($snapshot['request_number'] ?? null),
            'entity_name' => $snapshot['entity_name'] ?? null,
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_hijri' => ['ar' => HijriDate::format($issuedAt, 'ar', $timezone), 'en' => HijriDate::format($issuedAt, 'en', $timezone)],
            'issue_year' => (string) $issuedAt->year,
            'is_test' => $certificate->is_test,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accreditedData(Certificate $certificate): array
    {
        return [
            'percent' => ScalarValue::string($certificate->snapshot['percent'] ?? null),
            'level' => $certificate->level?->value,
            'level_label' => $certificate->level === null ? null : LocalizedText::fromTranslation('certificates.levels.'.$certificate->level->value)->toArray(),
        ];
    }

    /**
     * No score or percentage: an award certificate states the outcome only (03 §8.4).
     *
     * @return array<string, mixed>
     */
    private function awardData(Certificate $certificate): array
    {
        $award = is_array($certificate->snapshot['award'] ?? null) ? $certificate->snapshot['award'] : [];

        return [
            'kind' => $certificate->kind->value,
            'cycle_name' => $award['cycle_name'] ?? null,
            'cycle_year' => $award['cycle_year'] ?? null,
            'region' => $award['region'] ?? null,
            'regional_rank' => $award['regional_rank'] ?? null,
            'national_rank' => $award['national_rank'] ?? null,
        ];
    }

    public function fileNameOf(Certificate $certificate): string
    {
        return $certificate->number.'.pdf';
    }
}
