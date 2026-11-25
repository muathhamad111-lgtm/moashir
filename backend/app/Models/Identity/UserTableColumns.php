<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The columns one user keeps visible in one list table.
 *
 * @property int $id
 * @property int $user_id
 * @property string $table_key
 * @property list<string> $columns
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class UserTableColumns extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'table_key', 'columns'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'columns' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
