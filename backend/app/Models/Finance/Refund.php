<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\RefundStatus;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasPublicId;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $payment_id
 * @property string $amount
 * @property string $currency
 * @property Carbon $refunded_on
 * @property string $reason
 * @property string $transfer_reference
 * @property int $evidence_file_id
 * @property RefundStatus $status
 * @property int $recorded_by_user_id
 * @property Carbon $created_at
 * @property-read Payment $payment
 * @property-read StoredFile $evidenceFile
 * @property-read User $recordedBy
 */
final class Refund extends Model
{
    use AppendOnly;
    use HasPublicId;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['payment_id', 'amount', 'currency', 'refunded_on', 'reason', 'transfer_reference', 'evidence_file_id', 'status', 'recorded_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'amount' => 'decimal:2',
            'refunded_on' => 'date',
            'evidence_file_id' => 'integer',
            'status' => RefundStatus::class,
            'recorded_by_user_id' => 'integer',
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
    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'evidence_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id')->withTrashed();
    }
}
