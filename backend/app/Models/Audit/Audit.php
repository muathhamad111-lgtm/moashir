<?php

declare(strict_types=1);

namespace App\Models\Audit;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * @property int $id
 * @property string|null $user_type
 * @property int|null $user_id
 * @property string $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $request_id
 * @property list<string>|null $actor_roles
 * @property string|null $tags
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $actor_entity_id
 * @property Carbon $created_at
 */
final class Audit extends BaseAudit
{
    use AppendOnly;

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'actor_roles' => 'json',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'user_id');
    }
}
