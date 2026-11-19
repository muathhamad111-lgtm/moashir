<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use App\Enums\Notifications\NotificationCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $template_key
 * @property NotificationCode $code
 * @property array<string, array{ar: string, en: string}> $parts
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NotificationTextOverride extends Model implements Auditable
{
    use AuditableTrait;

    /**
     * @var list<string>
     */
    protected $fillable = ['template_key', 'code', 'parts', 'updated_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['code' => NotificationCode::class, 'parts' => 'array'];
    }
}
