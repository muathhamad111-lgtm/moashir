<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Assessments\EvidenceLevel;
use App\Enums\Assessments\EvidenceStatus;
use App\Enums\Files\MalwareScanStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $assessment_id
 * @property string|null $main_code
 * @property string|null $sub_code
 * @property string|null $attachment_key
 * @property int $stored_file_id
 * @property EvidenceStatus $status
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $removed_at
 * @property int|null $removed_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Assessment $assessment
 * @property-read StoredFile $storedFile
 */
final class EvidenceFile extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'main_code', 'sub_code', 'attachment_key', 'stored_file_id', 'status', 'uploaded_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'stored_file_id' => 'integer',
            'status' => EvidenceStatus::class,
            'uploaded_by_user_id' => 'integer',
            'removed_at' => 'datetime',
            'removed_by_user_id' => 'integer',
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
        return $this->belongsTo(StoredFile::class)->withTrashed();
    }

    public static function ownerColumn(EvidenceLevel $level): string
    {
        return $level === EvidenceLevel::Main ? 'main_code' : 'sub_code';
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofOwner(Builder $query, EvidenceLevel $level, string $code): void
    {
        $query->where(self::ownerColumn($level), $code);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function present(Builder $query): void
    {
        $query->where('status', EvidenceStatus::Uploaded);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function scannedClean(Builder $query): void
    {
        $query->whereHas('storedFile', static fn (Builder $file): Builder => $file->where('malware_scan_status', MalwareScanStatus::Clean));
    }

    public function level(): EvidenceLevel
    {
        return $this->main_code === null ? EvidenceLevel::Sub : EvidenceLevel::Main;
    }

    /**
     * The code of the main criterion or sub-criterion the file belongs to.
     */
    public function ownerCode(): string
    {
        return $this->main_code ?? (string) $this->sub_code;
    }

    public function isPresent(): bool
    {
        return $this->status === EvidenceStatus::Uploaded;
    }

    public function scanStatus(): MalwareScanStatus
    {
        return $this->storedFile->malware_scan_status;
    }

    public function countsTowardRequirements(): bool
    {
        return $this->isPresent() && in_array($this->scanStatus(), [MalwareScanStatus::Pending, MalwareScanStatus::Clean], true);
    }
}
