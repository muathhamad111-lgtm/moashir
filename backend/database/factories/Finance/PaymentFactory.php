<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => sprintf('PAY-2026-%06d', fake()->unique()->numberBetween(900_000, 999_999)),
            'entity_id' => Entity::factory()->withOwner(),
            'entity_name' => static fn (array $attributes): array => Entity::query()->whereKey(ScalarValue::int($attributes['entity_id'] ?? null))->firstOrFail()->name->toArray(),
            'initiated_by_user_id' => static fn (array $attributes): int => ScalarValue::int(User::query()->where('entity_id', ScalarValue::int($attributes['entity_id'] ?? null))->value('id')),
            'purpose' => PaymentPurpose::Accredited,
            'mode' => IntegrationMode::Live,
            'idempotency_key' => Str::uuid()->toString(),
            'price_before_vat' => '1000.00',
            'vat_rate' => '15.00',
            'vat_amount' => '150.00',
            'total' => '1150.00',
            'currency' => 'SAR',
            'status' => PaymentStatus::Created,
            'expires_at' => Date::now()->addMinutes(30),
        ];
    }

    public function forEntity(Entity $entity): self
    {
        return $this->state(['entity_id' => $entity->id]);
    }

    public function testMode(): self
    {
        return $this->state(['mode' => IntegrationMode::Test]);
    }

    public function bankTransfer(): self
    {
        return $this->state([
            'channel' => PaymentChannel::BankTransfer,
            'provider' => null,
            'status' => PaymentStatus::UnderReview,
            'expires_at' => null,
        ]);
    }

    public function paid(?string $providerPaymentId = null): self
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Paid,
            'provider_payment_id' => $providerPaymentId ?? Str::uuid()->toString(),
            'bound_at' => Date::now()->subMinutes(5),
            'paid_at' => Date::now()->subMinutes(4),
            'verified_at' => Date::now()->subMinutes(4),
        ]);
    }
}
