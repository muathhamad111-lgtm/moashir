<?php

declare(strict_types=1);

namespace App\Models\Settings;

use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SettingGroup $group
 * @property string $key
 * @property SettingType $type
 * @property mixed $value
 * @property bool $is_public
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['group', 'key', 'type'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => SettingGroup::class,
            'type' => SettingType::class,
            'value' => 'json',
            'is_public' => 'boolean',
            'updated_by_user_id' => 'integer',
        ];
    }
}
