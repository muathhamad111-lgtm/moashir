<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\PaidDraftReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PaidDraftReminder $resource
 */
final class PaidDraftReminderResource extends JsonResource
{
    /**
     * @return array{assessment_id: int, reminded_at: string, next_remind_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'assessment_id' => $this->resource->assessmentId,
            'reminded_at' => $this->resource->remindedAt->toIso8601String(),
            'next_remind_at' => $this->resource->nextRemindAt->toIso8601String(),
        ];
    }
}
