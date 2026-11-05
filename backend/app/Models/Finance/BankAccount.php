<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Casts\LocalizedTextCast;
use App\Models\Concerns\HasPublicId;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Database\Factories\Finance\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property LocalizedText $bank_name
 * @property LocalizedText $account_holder_name
 * @property string $iban
 * @property string|null $account_number
 * @property LocalizedText|null $payer_note
 * @property int $display_order
 * @property bool $is_active
 * @property Carbon $activated_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $archived_at
 * @property int $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $createdBy
 */
final class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_name', 'account_holder_name', 'iban', 'account_number', 'payer_note', 'display_order', 'is_active',
        'activated_at', 'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bank_name' => LocalizedTextCast::class,
            'account_holder_name' => LocalizedTextCast::class,
            'payer_note' => LocalizedTextCast::class,
            'display_order' => 'integer',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'archived_at' => 'datetime',
            'created_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    /**
     * @return HasMany<BankTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(BankTransfer::class);
    }

    public function isSelectable(): bool
    {
        return $this->is_active && $this->archived_at === null;
    }

    public function ibanLastFour(): string
    {
        return substr($this->iban, -4);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function listed(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function selectable(Builder $query): void
    {
        $query->whereNull('archived_at')->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inDisplayOrder(Builder $query): void
    {
        $query->orderBy('display_order')->orderBy('id');
    }
}
