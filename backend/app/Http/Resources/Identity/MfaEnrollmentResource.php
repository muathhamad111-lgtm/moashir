<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Data\Identity\MfaEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MfaEnrollment $resource
 */
final class MfaEnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'qr_svg' => $this->resource->qrCodeSvg,
            'manual_entry_key' => $this->resource->manualEntryKey,
        ];
    }
}
