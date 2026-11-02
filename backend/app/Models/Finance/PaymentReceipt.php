<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Casts\JsonObjectCast;
use App\Enums\Finance\ReceiptStatus;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Concerns\HasPublicId;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $payment_id
 * @property string|null $number
 * @property bool $is_test
 * @property ReceiptStatus $status
 * @property Carbon $issued_at
 * @property array<string, mixed> $snapshot
 * @property int|null $pdf_file_id
 * @property int $reissue_count
 * @property Carbon|null $last_reissued_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by_refund_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Payment $payment
 * @property-read StoredFile|null $pdfFile
 */
final class PaymentReceipt extends Model
{
    use HasPublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['reissue_count' => 0];

    /**
     * @var list<string>
     */
    protected $fillable = ['payment_id', 'number', 'is_test', 'status', 'issued_at', 'snapshot'];

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
            'payment_id' => 'integer',
            'is_test' => 'boolean',
            'status' => ReceiptStatus::class,
            'issued_at' => 'datetime',
            'snapshot' => JsonObjectCast::class,
            'pdf_file_id' => 'integer',
            'reissue_count' => 'integer',
            'last_reissued_at' => 'datetime',
            'voided_at' => 'datetime',
            'voided_by_refund_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pdf_file_id');
    }

    public function isVoided(): bool
    {
        return $this->status === ReceiptStatus::Voided;
    }
}
