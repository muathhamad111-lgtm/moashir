<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\AuthSettingsView;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\RoleKey;
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
            'general_email_notifications' => $this->resource->generalEmailNotifications,
            'two_factor_enabled' => $this->resource->twoFactorEnabled,
            'two_factor_roles' => array_map(static fn (RoleKey $role): string => $role->value, $this->resource->twoFactorRoles),
            'sms_status' => $this->resource->smsStatus->value,
        ];
    }
}
