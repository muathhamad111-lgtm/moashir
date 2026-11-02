<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\ThemePreference;
use App\Enums\Identity\UserStatus;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public const string PASSWORD = 'Nakheel-Riyadh-2026!';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('ar_SA')->name(),
            'email' => fake()->unique()->userName().'@moashir.example.sa',
            'locale' => Locale::Arabic,
            'theme' => ThemePreference::System,
            'status' => UserStatus::Active,
            'password' => self::PASSWORD,
            'email_verified_at' => Date::now(),
            'password_changed_at' => Date::now(),
        ];
    }

    public function withRole(RoleKey $role): self
    {
        return $this
            ->state(['mfa_required' => $role->isStaff(), 'entity_id' => $role->isStaff() ? null : Entity::factory()])
            ->afterCreating(static function (User $user) use ($role): void {
                $user->assignRole($role->value);
            });
    }

    public function withConfirmedTotp(?string $secret = null): self
    {
        $secret ??= (new Google2FA)->generateSecretKey();

        return $this->state([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code-one', 'recovery-code-two'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => Date::now(),
        ]);
    }

    public function invited(): self
    {
        return $this->state(['status' => UserStatus::Invited, 'password' => null, 'email_verified_at' => null]);
    }

    public function suspended(): self
    {
        return $this->state(['status' => UserStatus::Suspended, 'deactivated_at' => Date::now()]);
    }

    public function locked(): self
    {
        return $this->state(['locked_until' => Date::now()->addMinutes(15)]);
    }

    public function withoutPassword(): self
    {
        return $this->state(['password' => null]);
    }
}
