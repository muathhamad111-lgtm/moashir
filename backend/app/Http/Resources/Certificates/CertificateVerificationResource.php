<?php

declare(strict_types=1);

namespace App\Http\Resources\Certificates;

use App\Data\Certificates\CertificateVerification;
use App\Enums\Certificates\VerificationOutcome;
use App\Models\Certificates\Certificate;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CertificateVerification $resource
 */
final class CertificateVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $certificate = $this->resource->certificate;
        $outcome = $this->resource->outcome;

        return [
            'result' => $outcome->value,
            'certificate' => $certificate instanceof Certificate && $outcome->disclosesCertificate() ? $this->publicCardOf($certificate, $outcome) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicCardOf(Certificate $certificate, VerificationOutcome $outcome): array
    {
        $snapshot = $certificate->snapshot;
        $level = $outcome === VerificationOutcome::UnderReview ? null : $certificate->level;

        return [
            'number' => $certificate->number,
            'kind' => $certificate->kind->value,
            'level' => $level?->value,
            'level_label' => $level === null ? null : LocalizedText::fromTranslation('certificates.levels.'.$level->value)->toArray(),
            'percent' => $outcome === VerificationOutcome::UnderReview ? null : ScalarValue::string($snapshot['percent'] ?? null),
            'entity_name' => $snapshot['entity_name'] ?? null,
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'valid_until' => $certificate->valid_until?->toDateString(),
        ];
    }
}
