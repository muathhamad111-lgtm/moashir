<?php

declare(strict_types=1);

namespace App\Models\Files;

use App\Enums\Files\RenditionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_file_id
 * @property int|null $rendition_file_id
 * @property RenditionStatus $status
 * @property string|null $failure_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read StoredFile $source
 * @property-read StoredFile|null $rendition
 */
final class FileRendition extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['source_file_id', 'rendition_file_id', 'status', 'failure_code'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_file_id' => 'integer',
            'rendition_file_id' => 'integer',
            'status' => RenditionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'source_file_id')->withTrashed();
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function rendition(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'rendition_file_id');
    }

    public function isReady(): bool
    {
        return $this->status === RenditionStatus::Ready && $this->rendition_file_id !== null;
    }
}
