<?php

declare(strict_types=1);

namespace App\Http\Resources\Certificates;

use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Certificates\VerificationCode;
use App\Support\Assessments\PathPresenter;
use App\Support\Certificates\CertificateLinks;
use App\Support\Periods\LocalDay;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Certificate $resource
 */
final class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $certificate = $this->resource;
        $viewer = $request->user();
        $snapshot = $certificate->snapshot;
        $card = [
            'id' => $certificate->public_id,
            'number' => $certificate->number,
            'kind' => $certificate->kind->value,
            'level' => $certificate->level?->value,
            'level_label' => $certificate->level === null ? null : LocalizedText::fromTranslation('certificates.levels.'.$certificate->level->value)->toArray(),
            'status' => $certificate->status->value,
            'is_test' => $certificate->is_test,
            'percent' => ScalarValue::string($snapshot['percent'] ?? null),
            'request' => [
                'id' => $certificate->assessment_id,
                'number' => ScalarValue::string($snapshot['request_number'] ?? null),
                ...PathPresenter::of($certificate->assessment->path),
            ],
            'entity' => [
                'id' => $certificate->entity_id,
                'reference' => ScalarValue::string($snapshot['entity_reference'] ?? null),
                'name' => $snapshot['entity_name'] ?? null,
            ],
            'verification_code' => VerificationCode::display($certificate->verification_code),
            'verification_url' => CertificateLinks::verification($certificate->verification_code),
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'revoked_at' => $certificate->revoked_at?->toIso8601String(),
            'revoked_reason' => $certificate->revoked_reason,
            'superseded_at' => $certificate->superseded_at?->toIso8601String(),
            'suspended_at' => $certificate->suspended_at?->toIso8601String(),
            'valid_until' => $certificate->valid_until?->toDateString(),
            'valid_until_at' => $certificate->validUntilInstant()?->toIso8601String(),
            'is_expired' => $certificate->isExpiredOn(LocalDay::today()),
            'replaces' => $certificate->replaces instanceof Certificate ? ['id' => $certificate->replaces->public_id, 'number' => $certificate->replaces->number] : null,
            'has_pdf' => $certificate->pdf_file_id !== null,
        ];

        return $viewer instanceof User && $certificate->isOwnedBy($viewer->entity_id) ? $card : [...$card, ...$this->staffFields($certificate)];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffFields(Certificate $certificate): array
    {
        return [
            'issued_by' => $certificate->issuedBy instanceof User ? ['id' => $certificate->issuedBy->id, 'name' => $certificate->issuedBy->name] : null,
            'revoked_by' => $certificate->revokedBy instanceof User ? ['id' => $certificate->revokedBy->id, 'name' => $certificate->revokedBy->name] : null,
            'reissue_reason' => $certificate->reissue_reason,
        ];
    }
}
