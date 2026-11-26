<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Contracts\Identity\OneTimeCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One e-mail check of the registration form: the code sent to the official address and, once the
 * code is verified, the single-use token the registration must present.
 *
 * @property int $id
 * @property string $challenge_id
 * @property string $email
 * @property string $email_index
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $consumed_at
 * @property Carbon|null $voided_at
 * @property Carbon $last_sent_at
 * @property string|null $token_hash
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $token_used_at
 * @property string|null $ip_address
 */
final class RegistrationEmailVerification extends Model implements OneTimeCode
{
    protected $fillable = [
        'challenge_id', 'email', 'email_index', 'code_hash', 'expires_at', 'last_sent_at', 'ip_address',
    ];

    protected $hidden = ['email', 'email_index', 'code_hash', 'token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'consumed_at' => 'datetime',
            'voided_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'token_used_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->consumed_at === null && $this->voided_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function failedAttempts(): int
    {
        return $this->attempts;
    }

    public function codeHash(): string
    {
        return $this->code_hash;
    }

    public function hasUsableToken(): bool
    {
        return $this->token_used_at === null && $this->token_expires_at?->isFuture() === true;
    }
}
