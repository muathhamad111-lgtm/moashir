<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Identity\User;
use App\Support\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\Finance\ServicePriceVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PaymentPurpose $purpose
 * @property string $price_before_vat
 * @property PathAvailability $availability
 * @property Carbon $effective_from
 * @property int|null $previous_version_id
 * @property int|null $set_by_user_id
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property Carbon $created_at
 * @property-read ServicePriceVersion|null $previousVersion
 * @property-read User|null $setBy
 * @property-read User|null $cancelledBy
 */
final class ServicePriceVersion extends Model
{
    /** @use HasFactory<ServicePriceVersionFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['purpose', 'price_before_vat', 'availability', 'effective_from', 'previous_version_id', 'set_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'price_before_vat' => 'decimal:2',
            'availability' => PathAvailability::class,
            'effective_from' => 'datetime',
            'previous_version_id' => 'integer',
            'set_by_user_id' => 'integer',
            'cancelled_at' => 'datetime',
            'cancelled_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ServicePriceVersion, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id')->withTrashed();
    }

    public function price(): Money
    {
        return new Money($this->price_before_vat);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isScheduledAt(CarbonInterface $moment): bool
    {
        return ! $this->isCancelled() && $this->effective_from->greaterThan($moment);
    }

    public function matches(Money $price, PathAvailability $availability): bool
    {
        return $this->price()->equals($price) && $this->availability === $availability;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function of(Builder $query, PaymentPurpose $purpose): void
    {
        $query->where('purpose', $purpose);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function effectiveBy(Builder $query, CarbonInterface $moment): void
    {
        $query->where('effective_from', '<=', $moment)->orderByDesc('effective_from')->orderByDesc('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function scheduledAfter(Builder $query, CarbonInterface $moment): void
    {
        $query->where('effective_from', '>', $moment)->orderBy('effective_from')->orderBy('id');
    }
}
