<?php

declare(strict_types=1);

namespace App\Models\Certificates;

use App\Casts\JsonObjectCast;
use App\Casts\PercentCast;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Certificates\CertificateStatus;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Assessments\Assessment;
use App\Models\Concerns\HasPublicId;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Support\Periods\LocalDay;
use App\Support\ValueObjects\CertificateValidityStatus;
use App\Support\ValueObjects\Percent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $number
 * @property string $verification_code
 * @property CertificateKind $kind
 * @property CertificateLevel|null $level
 * @property CertificateStatus $status
 * @property bool $is_test
 * @property int $entity_id
 * @property int $assessment_id
 * @property int $result_snapshot_id
 * @property Percent $score_percent
 * @property array<string, mixed> $snapshot
 * @property Carbon $issued_at
 * @property int|null $issued_by_user_id
 * @property int|null $pdf_file_id
 * @property int|null $entity_logo_file_id
 * @property int|null $replaces_certificate_id
 * @property string|null $reissue_reason
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property int|null $revoked_by_user_id
 * @property Carbon|null $superseded_at
 * @property Carbon|null $suspended_at
 * @property CarbonImmutable|null $valid_until
 * @property string|null $suspended_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Entity $entity
 * @property-read Assessment $assessment
 * @property-read StoredFile|null $pdfFile
 * @property-read Certificate|null $replaces
 * @property-read User|null $issuedBy
 * @property-read User|null $revokedBy
 */
final class Certificate extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number', 'verification_code', 'kind', 'level', 'status', 'is_test', 'entity_id', 'assessment_id',
        'result_snapshot_id', 'score_percent', 'snapshot', 'issued_at', 'issued_by_user_id', 'replaces_certificate_id',
        'reissue_reason', 'entity_logo_file_id', 'valid_until',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['issued_by_user_id', 'revoked_by_user_id', 'revoked_reason', 'reissue_reason', 'suspended_reason'];

    protected static function booted(): void
    {
        self::deleting(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CertificateKind::class,
            'level' => CertificateLevel::class,
            'status' => CertificateStatus::class,
            'is_test' => 'boolean',
            'entity_id' => 'integer',
            'assessment_id' => 'integer',
            'result_snapshot_id' => 'integer',
            'score_percent' => PercentCast::class,
            'snapshot' => JsonObjectCast::class,
            'issued_at' => 'datetime',
            'issued_by_user_id' => 'integer',
            'pdf_file_id' => 'integer',
            'entity_logo_file_id' => 'integer',
            'replaces_certificate_id' => 'integer',
            'revoked_at' => 'datetime',
            'revoked_by_user_id' => 'integer',
            'superseded_at' => 'datetime',
            'suspended_at' => 'datetime',
            'valid_until' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pdf_file_id');
    }

    /**
     * @return BelongsTo<Certificate, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_certificate_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, int $entityId): void
    {
        $query->where('entity_id', $entityId);
    }

    public function isExpiredOn(CarbonImmutable $day): bool
    {
        return $this->isIssued() && $this->valid_until instanceof CarbonImmutable && $this->valid_until->toDateString() < $day->toDateString();
    }

    public function validityOn(CarbonImmutable $day, int $expiringSoonDays): ?CertificateValidityStatus
    {
        return $this->isIssued() ? CertificateValidityStatus::on($this->valid_until, $day, $expiringSoonDays) : null;
    }

    public function validUntilInstant(): ?CarbonImmutable
    {
        return $this->valid_until instanceof CarbonImmutable ? LocalDay::endOfDayInstant($this->valid_until) : null;
    }

    public function isIssued(): bool
    {
        return $this->status === CertificateStatus::Issued;
    }

    public function isSuspended(): bool
    {
        return $this->status === CertificateStatus::Suspended;
    }

    public function isInForceOrSuspended(): bool
    {
        return $this->isIssued() || $this->isSuspended();
    }

    public function isOwnedBy(?int $entityId): bool
    {
        return $entityId !== null && $this->entity_id === $entityId;
    }
}
