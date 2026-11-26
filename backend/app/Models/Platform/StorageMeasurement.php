<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $measured_on
 * @property int $used_bytes
 * @property int $file_count
 * @property Carbon $measured_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StorageMeasurement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['measured_on', 'used_bytes', 'file_count', 'measured_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'used_bytes' => 'integer',
            'file_count' => 'integer',
            'measured_at' => 'datetime',
        ];
    }
}
