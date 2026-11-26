<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Enums\Identity\RoleKey;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<BankAccount>
 */
final class BankAccountFactory extends Factory
{
    public const string EXAMPLE_IBAN = 'SA0380000000608010167519';

    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_name' => ['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)'],
            'account_holder_name' => ['ar' => 'منصة مؤشر (مثال)', 'en' => 'Moashir Platform (example)'],
            'iban' => self::EXAMPLE_IBAN,
            'account_number' => null,
            'payer_note' => null,
            'display_order' => fake()->unique()->numberBetween(1, 30_000),
            'is_active' => true,
            'activated_at' => Date::now(),
            'created_by_user_id' => static fn (): int => User::factory()->create()->assignRole(RoleKey::SystemAdmin->value)->id,
        ];
    }

    public function withIban(string $iban): self
    {
        return $this->state(['iban' => $iban]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false, 'deactivated_at' => Date::now()]);
    }
}
