<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserTableColumns;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class PreferencesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->resource->locale->value,
            'theme' => $this->resource->theme->value,
            // An object keyed by table, even when empty (a JSON array would read as a list).
            'table_columns' => (object) $this->resource->tableColumns
                ->mapWithKeys(static fn (UserTableColumns $choice): array => [$choice->table_key => $choice->columns])
                ->all(),
        ];
    }
}
