<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Casts\JsonObjectCast;
use App\Enums\Finance\FinanceExportStatus;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Concerns\HasPublicId;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $requested_by_user_id
 * @property array<string, mixed> $filters
 * @property int $row_count
 * @property string $locale
 * @property FinanceExportStatus $status
 * @property int|null $stored_file_id
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read StoredFile|null $storedFile
 */
final class FinanceExport extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = ['requested_by_user_id', 'filters', 'row_count', 'locale', 'status'];

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
            'requested_by_user_id' => 'integer',
            'filters' => JsonObjectCast::class,
            'row_count' => 'integer',
            'status' => FinanceExportStatus::class,
            'stored_file_id' => 'integer',
            'completed_at' => 'datetime',
        ];
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
}
