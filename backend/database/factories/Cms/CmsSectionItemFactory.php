<?php

declare(strict_types=1);

namespace Database\Factories\Cms;

use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsSectionItem>
 */
final class CmsSectionItemFactory extends Factory
{
    protected $model = CmsSectionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cms_section_id' => CmsSection::factory(),
            'is_enabled' => true,
            'sort_order' => fake()->unique()->numberBetween(1, 30_000),
            'title' => ['ar' => 'التقييم الذاتي', 'en' => 'Self assessment'],
            'description' => ['ar' => 'تقييم أولي سريع يساعد الجهة على معرفة مستوى جاهزيتها.', 'en' => 'A quick first assessment that shows the entity its readiness.'],
            'icon' => 'fa-solid fa-list-check',
        ];
    }

    public function disabled(): self
    {
        return $this->state(['is_enabled' => false]);
    }
}
