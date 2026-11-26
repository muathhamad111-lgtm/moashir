<?php

declare(strict_types=1);

namespace App\Models\Files;

use App\Enums\Files\AccessIntent;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Concerns\AppendOnly;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $trace_code
 * @property int $user_id
 * @property int $stored_file_id
 * @property string $stored_file_public_id
 * @property StoredFilePurpose $purpose
 * @property AccessIntent $intent
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $user
 */
final class FileAccessTrace extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['trace_code', 'user_id', 'stored_file_id', 'stored_file_public_id', 'purpose', 'intent', 'ip_address', 'user_agent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'stored_file_id' => 'integer',
            'purpose' => StoredFilePurpose::class,
            'intent' => AccessIntent::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
