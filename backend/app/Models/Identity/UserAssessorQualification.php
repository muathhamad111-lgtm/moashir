<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\AssessorQualification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property AssessorQualification $qualification
 * @property int|null $granted_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class UserAssessorQualification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'qualification', 'granted_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'qualification' => AssessorQualification::class,
            'granted_by_user_id' => 'integer',
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
