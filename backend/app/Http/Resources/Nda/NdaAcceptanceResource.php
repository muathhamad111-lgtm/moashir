<?php

declare(strict_types=1);

namespace App\Http\Resources\Nda;

use App\Models\Identity\TermsAcceptance;
use App\Support\Nda\MaskedIpAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded acceptance of an agreement version, with the IP address masked.
 *
 * @property-read TermsAcceptance $resource
 */
final class NdaAcceptanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'terms_version_id' => $this->resource->terms_version_id,
            'version' => $this->resource->termsVersion?->version,
            'title' => $this->resource->termsVersion?->title?->toArray(),
            'effective_from' => $this->resource->termsVersion?->effective_from?->toDateString(),
            'accepted_at' => $this->resource->created_at->toIso8601String(),
            'accepted_roles' => $this->resource->accepted_roles ?? [],
            'ip_masked' => MaskedIpAddress::of($this->resource->ip_address),
        ];
    }
}
