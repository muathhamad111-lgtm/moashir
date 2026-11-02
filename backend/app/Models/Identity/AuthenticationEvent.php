<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email_attempted
 * @property AuthEventType $type
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property array<string, scalar>|null $meta
 * @property Carbon $created_at
 */
final class AuthenticationEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = ['user_id', 'email_attempted', 'type', 'ip_address', 'user_agent', 'request_id', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AuthEventType::class,
            'meta' => 'array',
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
