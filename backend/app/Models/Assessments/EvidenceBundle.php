<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Assessments\EvidenceBundleStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Files\StoredFile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $assessment_id
 * @property int $requested_by_user_id
 * @property EvidenceBundleStatus $status
 * @property int $file_count
 * @property int $size_bytes
 * @property int|null $stored_file_id
 * @property Carbon|null $ready_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Assessment $assessment
 * @property-read StoredFile|null $storedFile
 */
final class EvidenceBundle extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'requested_by_user_id', 'status', 'file_count', 'size_bytes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'status' => EvidenceBundleStatus::class,
            'file_count' => 'integer',
            'size_bytes' => 'integer',
            'stored_file_id' => 'integer',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
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
    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }

    public function isRequestedBy(int $userId): bool
    {
        return $this->requested_by_user_id === $userId;
    }

    public function isDownloadable(CarbonInterface $now): bool
    {
        return $this->status === EvidenceBundleStatus::Ready && $this->expires_at instanceof Carbon && $this->expires_at->greaterThan($now);
    }
}
