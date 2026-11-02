<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $challenge_id
 * @property int $user_id
 * @property OtpChannel $channel
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property string $destination_masked
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $consumed_at
 * @property Carbon|null $voided_at
 * @property Carbon $last_sent_at
 * @property string|null $ip_address
 * @property-read User $user
 */
final class OtpCode extends Model
{
    protected $fillable = [
        'challenge_id', 'user_id', 'channel', 'purpose', 'code_hash', 'destination_masked',
        'expires_at', 'last_sent_at', 'ip_address',
    ];

    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OtpChannel::class,
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'consumed_at' => 'datetime',
            'voided_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->consumed_at === null && $this->voided_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
