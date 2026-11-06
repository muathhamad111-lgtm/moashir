<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\TransferRejectionReason;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $bank_account_id
 * @property int $proof_file_id
 * @property int $submitted_by_user_id
 * @property Carbon $submitted_at
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property TransferRejectionReason|null $rejection_reason
 * @property string|null $review_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Payment $payment
 * @property-read BankAccount $bankAccount
 * @property-read StoredFile $proofFile
 * @property-read User|null $reviewedBy
 */
final class BankTransfer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['payment_id', 'bank_account_id', 'proof_file_id', 'submitted_by_user_id', 'submitted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'bank_account_id' => 'integer',
            'proof_file_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_by_user_id' => 'integer',
            'reviewed_at' => 'datetime',
            'rejection_reason' => TransferRejectionReason::class,
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
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'proof_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')->withTrashed();
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }
}
