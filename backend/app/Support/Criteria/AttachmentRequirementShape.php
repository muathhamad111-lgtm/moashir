<?php

declare(strict_types=1);

namespace App\Support\Criteria;

use App\Models\Criteria\AttachmentRequirement;

/**
 * One shape for an attachment requirement wherever it leaves the criteria: the trees, the edit snapshots and the
 * node resources, so a field added here reaches every reader at once.
 */
final class AttachmentRequirementShape
{
    /**
     * @return array{key: string, name: array{ar: string, en: string}, guidance: array{ar: string, en: string}|null, required: bool, allowed_types: list<string>|null, max_files: int|null}
     */
    public static function of(AttachmentRequirement $requirement): array
    {
        return [
            'key' => $requirement->key,
            'name' => $requirement->name->toArray(),
            'guidance' => $requirement->guidance?->toArray(),
            'required' => $requirement->is_required,
            'allowed_types' => $requirement->allowed_types,
            'max_files' => $requirement->max_files,
        ];
    }
}
