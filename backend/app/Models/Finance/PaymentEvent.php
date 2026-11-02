<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Casts\JsonObjectCast;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\Persistence\AppendOnlyViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $payment_id
 * @property string $provider
 * @property string $provider_event_id
 * @property PaymentEventSource $source
 * @property string $event_type
 * @property IntegrationMode|null $mode
 * @property bool $signature_valid
 * @property string|null $provider_payment_id
 * @property array<string, mixed> $payload
 * @property string|null $ip_address
 * @property int|null $actor_user_id
 * @property Carbon|null $processed_at
 * @property PaymentEventResult|null $processing_result
 * @property string|null $error_code
 * @property Carbon $created_at
 * @property-read Payment|null $payment
 */
final class PaymentEvent extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['payload' => '{}', 'signature_valid' => false];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id', 'provider', 'provider_event_id', 'source', 'event_type', 'mode', 'signature_valid',
        'provider_payment_id', 'payload', 'ip_address', 'actor_user_id', 'processed_at', 'processing_result', 'error_code',
    ];

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
            'source' => PaymentEventSource::class,
            'mode' => IntegrationMode::class,
            'signature_valid' => 'boolean',
            'payload' => JsonObjectCast::class,
            'actor_user_id' => 'integer',
            'processed_at' => 'datetime',
            'processing_result' => PaymentEventResult::class,
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
