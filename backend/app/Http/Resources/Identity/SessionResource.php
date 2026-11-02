<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read UserSession $resource
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->publicId(),
            'ip' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent === null ? null : mb_substr($this->resource->user_agent, 0, 255),
            'last_activity_at' => Carbon::createFromTimestamp($this->resource->last_activity)->toIso8601String(),
            'current' => $request->hasSession() && $request->session()->getId() === $this->resource->id,
        ];
    }
}
