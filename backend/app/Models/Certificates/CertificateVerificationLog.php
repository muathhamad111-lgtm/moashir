<?php

declare(strict_types=1);

namespace App\Models\Certificates;

use App\Enums\Certificates\VerificationOutcome;
use App\Exceptions\Persistence\AppendOnlyViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $certificate_id
 * @property VerificationOutcome $outcome
 * @property string $ip_hash
 * @property Carbon $created_at
 */
final class CertificateVerificationLog extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['certificate_id', 'outcome', 'ip_hash'];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'certificate_id' => 'integer',
            'outcome' => VerificationOutcome::class,
        ];
    }
}
