<?php

declare(strict_types=1);

namespace Database\Factories\Entities;

use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityStatus;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Identity\RoleKey;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Entity>
 */
final class EntityFactory extends Factory
{
    protected $model = Entity::class;

    private const array NAMES = [
        ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
        ['ar' => 'مؤسسة النماء الأهلية', 'en' => 'Al-Namaa Civil Foundation'],
        ['ar' => 'وقف الخير التنموي', 'en' => 'Al-Khair Development Waqf'],
        ['ar' => 'جمعية البر الأهلية', 'en' => 'Al-Birr Civil Association'],
        ['ar' => 'مؤسسة رواد العطاء الأهلية', 'en' => 'Ruwad Al-Ataa Civil Foundation'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::NAMES),
            'entity_type' => fake()->randomElement(EntityType::cases()),
            'development_sector' => fake()->randomElement(DevelopmentSector::cases()),
            'org_size' => fake()->randomElement(OrgSize::cases()),
            'founding_year' => fake()->numberBetween(1990, 2022),
            'region_id' => Region::factory(),
            'city_id' => static fn (array $attributes): int => City::factory()->create(['region_id' => $attributes['region_id']])->id,
            'governance_grade' => '82.50',
            'employees_count' => fake()->numberBetween(5, 200),
            'annual_beneficiaries_count' => fake()->numberBetween(100, 40_000),
            'has_strategic_plan' => true,
            'status' => EntityStatus::Active,
            'verified_at' => Date::now(),
            'profile_completed_at' => Date::now(),
        ];
    }

    public function named(string $arabic, ?string $english = null): self
    {
        return $this->state(['name' => ['ar' => $arabic, 'en' => $english]]);
    }

    public function pendingVerification(): self
    {
        return $this->state(['status' => EntityStatus::PendingVerification, 'verified_at' => null]);
    }

    public function deactivated(): self
    {
        return $this->state(['status' => EntityStatus::Deactivated, 'deactivated_at' => Date::now(), 'deactivation_reason' => 'توقف نشاط الجهة مؤقتًا بطلب من مجلس الإدارة.']);
    }

    public function incompleteProfile(): self
    {
        return $this->state(['governance_grade' => null, 'employees_count' => null, 'annual_beneficiaries_count' => null, 'profile_completed_at' => null]);
    }

    public function withOwner(): self
    {
        return $this->afterCreating(static function (Entity $entity): void {
            User::factory()->withRole(RoleKey::Entity)->create([
                'entity_id' => $entity->id,
                'email_verified_at' => $entity->verified_at,
            ]);
        });
    }
}
