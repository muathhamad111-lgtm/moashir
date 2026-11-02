<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\Platform\DemoRecordKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property DemoRecordKind $kind
 * @property int $record_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class DemoRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'record_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DemoRecordKind::class,
            'record_id' => 'integer',
        ];
    }
}
