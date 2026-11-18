<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CriteriaEditEvent $resource
 */
final class CriteriaEditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'id' => $event->id,
            'kind' => $event->kind->value,
            'scope' => $event->scope->value,
            'from_version' => $this->versionOf($event->fromVersion),
            'to_version' => $this->versionOf($event->toVersion),
            'change_count' => count($event->changes),
            'score_affecting_count' => count(array_filter($event->changes, static fn (array $change): bool => ($change['score_affecting'] ?? false) === true)),
            'changes' => $event->changes,
            'affected_open_count' => $event->affected_open_count,
            'reason' => $event->reason,
            'edited_by' => $event->editor === null ? null : ['id' => $event->editor->id, 'name' => $event->editor->name],
            'created_at' => $event->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, version_number: int, label: array<string, string>|null}|null
     */
    private function versionOf(?CriteriaVersion $version): ?array
    {
        return $version === null ? null : [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'label' => $version->label?->toArray(),
        ];
    }
}
