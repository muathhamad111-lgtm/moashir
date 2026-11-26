<?php

declare(strict_types=1);

namespace Database\Factories\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsSection>
 */
final class CmsSectionFactory extends Factory
{
    protected $model = CmsSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->unique()->numberBetween(1, 30_000);

        return [
            'cms_page_id' => static fn (): int => ScalarValue::int(CmsPage::query()->where('key', CmsPageKey::Home)->valueOrFail('id')),
            'key' => 'section-'.$position,
            'type' => CmsSectionType::RichText,
            'is_enabled' => true,
            'sort_order' => $position,
            'title' => ['ar' => 'عن منصة مؤشر', 'en' => 'About the Moashir platform'],
            'description' => ['ar' => 'منصة لقياس نضج الجهات غير الربحية وتميزها المؤسسي.', 'en' => 'A platform that measures the maturity and institutional excellence of non-profit entities.'],
        ];
    }

    public function onPage(CmsPageKey $key): self
    {
        return $this->state(['cms_page_id' => static fn (): int => ScalarValue::int(CmsPage::query()->where('key', $key)->valueOrFail('id'))]);
    }

    public function ofType(CmsSectionType $type): self
    {
        return $this->state(['type' => $type]);
    }

    public function disabled(): self
    {
        return $this->state(['is_enabled' => false]);
    }
}
