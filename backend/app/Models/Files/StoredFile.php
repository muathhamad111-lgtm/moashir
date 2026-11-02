<?php

declare(strict_types=1);

namespace App\Models\Files;

use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Concerns\HasPublicId;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property StoredFilePurpose $purpose
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $extension
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property FileVisibility $visibility
 * @property string $content_class
 * @property MalwareScanStatus $malware_scan_status
 * @property Carbon|null $malware_scanned_at
 * @property string|null $scan_engine
 * @property Carbon|null $quarantined_at
 * @property string|null $attachable_type
 * @property int|null $attachable_id
 * @property int|null $uploaded_by_user_id
 * @property bool $is_encrypted_at_rest
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Model|null $attachable
 * @property-read User|null $uploadedBy
 */
final class StoredFile extends Model
{
    use HasPublicId;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purpose', 'disk', 'path', 'original_name', 'extension', 'mime_type', 'size_bytes', 'sha256', 'visibility',
        'content_class', 'malware_scan_status', 'malware_scanned_at', 'scan_engine', 'uploaded_by_user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['disk', 'path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => StoredFilePurpose::class,
            'size_bytes' => 'integer',
            'visibility' => FileVisibility::class,
            'malware_scan_status' => MalwareScanStatus::class,
            'malware_scanned_at' => 'datetime',
            'quarantined_at' => 'datetime',
            'attachable_id' => 'integer',
            'uploaded_by_user_id' => 'integer',
            'is_encrypted_at_rest' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id')->withTrashed();
    }

    public function isScannedClean(): bool
    {
        return $this->malware_scan_status === MalwareScanStatus::Clean;
    }

    public function isAwaitingScan(): bool
    {
        return $this->malware_scan_status === MalwareScanStatus::Pending;
    }

    public function needsQuarantine(): bool
    {
        return $this->malware_scan_status === MalwareScanStatus::Infected && $this->quarantined_at === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofPurpose(Builder $query, StoredFilePurpose $purpose): void
    {
        $query->where('purpose', $purpose);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function clean(Builder $query): void
    {
        $query->where('malware_scan_status', MalwareScanStatus::Clean);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingScan(Builder $query): void
    {
        $query->where('malware_scan_status', MalwareScanStatus::Pending);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function orphaned(Builder $query): void
    {
        $query->whereNull('attachable_type')->whereIn('purpose', StoredFilePurpose::ownerBoundValues());
    }
}
