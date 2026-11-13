<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\AuthSettingsView;
use App\Enums\Identity\OtpChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthSettingsView $resource
 */
final class AuthSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'entity_sign_in_code_channels' => array_map(static fn (OtpChannel $channel): string => $channel->value, $this->resource->entitySignInCodeChannels),
            'sms_status' => $this->resource->smsStatus->value,
            'staff_totp_required' => true,
        ];
    }
}
