<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Casts\EntityNameCast;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Award\AwardCycle;
use App\Models\Concerns\HasPublicId;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\Money;
use Database\Factories\Finance\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $reference
 * @property int $entity_id
 * @property EntityName $entity_name
 * @property int $initiated_by_user_id
 * @property PaymentPurpose $purpose
 * @property int|null $award_cycle_id
 * @property PaymentChannel $channel
 * @property string|null $provider
 * @property IntegrationMode $mode
 * @property string $idempotency_key
 * @property string|null $provider_intent_id
 * @property string|null $checkout_client_secret
 * @property string|null $provider_payment_id
 * @property PaymentInstrument|null $method
 * @property string|null $card_last4
 * @property string $price_before_vat
 * @property string $vat_rate
 * @property string $vat_amount
 * @property string $total
 * @property string $currency
 * @property PaymentStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $bound_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $late_paid_at
 * @property Carbon|null $manually_settled_at
 * @property PaymentFailureReason|null $failure_reason
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Entity $entity
 * @property-read User $initiatedBy
 * @property-read PaymentReceipt|null $receipt
 * @property-read Refund|null $refund
 * @property-read BankTransfer|null $bankTransfer
 * @property-read AwardCycle|null $awardCycle
 */
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference', 'entity_id', 'entity_name', 'initiated_by_user_id', 'purpose', 'award_cycle_id', 'channel', 'provider', 'mode', 'idempotency_key',
        'price_before_vat', 'vat_rate', 'vat_amount', 'total', 'currency', 'status', 'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['checkout_client_secret'];

    /**
     * @var array<string, string>
     */
    protected $attributes = ['channel' => 'electronic', 'provider' => 'moyasar'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'entity_name' => EntityNameCast::class,
            'initiated_by_user_id' => 'integer',
            'purpose' => PaymentPurpose::class,
            'award_cycle_id' => 'integer',
            'channel' => PaymentChannel::class,
            'mode' => IntegrationMode::class,
            'checkout_client_secret' => 'encrypted',
            'method' => PaymentInstrument::class,
            'price_before_vat' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'bound_at' => 'datetime',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'late_paid_at' => 'datetime',
            'manually_settled_at' => 'datetime',
            'failure_reason' => PaymentFailureReason::class,
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
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<AwardCycle, $this>
     */
    public function awardCycle(): BelongsTo
    {
        return $this->belongsTo(AwardCycle::class);
    }

    /**
     * @return HasOne<PaymentReceipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    /**
     * @return HasOne<Refund, $this>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * @return HasOne<BankTransfer, $this>
     */
    public function bankTransfer(): HasOne
    {
        return $this->hasOne(BankTransfer::class);
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function totalMoney(): Money
    {
        return new Money($this->total, $this->currency);
    }

    public function isOwnedBy(?int $entityId): bool
    {
        return $entityId !== null && $this->entity_id === $entityId;
    }

    public function isBound(): bool
    {
        return $this->provider_payment_id !== null;
    }

    public function isBankTransfer(): bool
    {
        return $this->channel === PaymentChannel::BankTransfer;
    }

    public function wasPaidAfterWindowOf(AwardCycle $cycle): bool
    {
        return $this->paid_at !== null && $cycle->closedSubmissionsBefore($this->paid_at);
    }

    public function isTest(): bool
    {
        return $this->mode->isTest();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, int $entityId): void
    {
        $query->where('entity_id', $entityId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', PaymentStatus::openValues());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forService(Builder $query, PaymentPurpose $purpose, ?int $awardCycleId): void
    {
        $query->where('purpose', $purpose)->when($purpose->isPerCycle(), static fn (Builder $inner): Builder => $inner->where('award_cycle_id', $awardCycleId));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function bankTransfers(Builder $query): void
    {
        $query->where('channel', PaymentChannel::BankTransfer);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('mode', IntegrationMode::Live);
    }
}
