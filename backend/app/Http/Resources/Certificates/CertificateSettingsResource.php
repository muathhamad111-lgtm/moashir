<?php

declare(strict_types=1);

namespace App\Http\Resources\Certificates;

use App\Data\Certificates\CertificateSettingsView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CertificateSettingsView $resource
 */
final class CertificateSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'accredited_issuance_enabled' => $this->resource->accreditedIssuanceEnabled,
            'level_thresholds' => $this->resource->levelThresholds->toArray(),
        ];
    }
}
