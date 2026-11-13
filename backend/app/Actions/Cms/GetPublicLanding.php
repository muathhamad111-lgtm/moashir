<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Services\Cms\PublishedPageReader;

final readonly class GetPublicLanding
{
    private const array CARD_FIELDS = ['title', 'subtitle', 'description', 'cta_label', 'cta_url', 'icon', 'image'];

    public function __construct(private PublishedPageReader $pages) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $home = $this->pages->read(CmsPageKey::Home);

        if ($home === null) {
            return ['published' => false, 'maintenance' => true];
        }

        $sections = is_array($home['sections'] ?? null) ? $home['sections'] : [];
        $paths = $this->firstOfType($sections, CmsSectionType::Paths);

        return [
            'published' => true,
            'maintenance' => false,
            'hero' => $this->card($this->firstOfType($sections, CmsSectionType::Hero)),
            'path_cards' => array_map($this->card(...), is_array($paths['items'] ?? null) ? array_values(array_filter($paths['items'], is_array(...))) : []),
            'sections' => [
                'bag' => $this->card($this->firstOfType($sections, CmsSectionType::BagPromo)),
                'lenses' => $this->card($this->firstOfType($sections, CmsSectionType::LensesBrief)),
                'leadership' => $this->card($this->firstOfType($sections, CmsSectionType::LeadershipPromo)),
            ],
            'published_at' => $home['published_at'] ?? null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $sections
     * @return array<array-key, mixed>|null
     */
    private function firstOfType(array $sections, CmsSectionType $type): ?array
    {
        foreach ($sections as $section) {
            if (is_array($section) && ($section['type'] ?? null) === $type->value) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>|null  $content
     * @return array<string, mixed>|null
     */
    private function card(?array $content): ?array
    {
        if ($content === null) {
            return null;
        }

        $card = ['id' => $content['id'] ?? null];

        foreach (self::CARD_FIELDS as $field) {
            $card[$field] = $content[$field] ?? null;
        }

        return $card;
    }
}
