<?php

declare(strict_types=1);

namespace App\Http\Resources\Nda;

use App\Data\Nda\NdaOverview;
use App\Enums\Identity\RoleKey;
use App\Http\Requests\Nda\UpdateNdaSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read NdaOverview $resource
 */
final class NdaOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'current' => $this->resource->current === null ? null : new NdaVersionSummaryResource($this->resource->current)->resolve($request),
            'draft' => $this->resource->draft === null ? null : new NdaVersionSummaryResource($this->resource->draft)->resolve($request),
            'roles' => $this->resource->roles,
            'available_roles' => RoleKey::staffValues(),
            'reminder_days' => $this->resource->reminderDays,
            'max_reminder_days' => UpdateNdaSettingsRequest::MAX_REMINDER_DAYS,
            'next_manual_reminder_at' => $this->resource->nextManualReminderAt?->toIso8601String(),
        ];
    }
}
