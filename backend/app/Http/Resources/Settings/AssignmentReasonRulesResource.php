<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Assessments\AssignmentReasonRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssignmentReasonRules $resource
 */
final class AssignmentReasonRulesResource extends JsonResource
{
    /**
     * @return array{assign_required: bool, reassign_required: bool}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
