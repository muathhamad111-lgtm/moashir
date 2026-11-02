<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Models\Entities\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property PaymentPurpose $purpose
 * @property Carbon $last_seen_at
 * @property int|null $converted_payment_id
 * @property Carbon|null $contacted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Entity $entity
 */
final class CheckoutIntent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['entity_id', 'purpose', 'last_seen_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'purpose' => PaymentPurpose::class,
            'last_seen_at' => 'datetime',
            'converted_payment_id' => 'integer',
            'contacted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
