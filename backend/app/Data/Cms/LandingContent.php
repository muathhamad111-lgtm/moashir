<?php

declare(strict_types=1);

namespace App\Data\Cms;

final readonly class LandingContent
{
    /**
     * @param  array<array-key, mixed>  $page
     * @param  list<array<array-key, mixed>>  $headerLinks
     * @param  list<array<array-key, mixed>>  $quickLinks
     */
    public function __construct(public array $page, public array $headerLinks, public array $quickLinks) {}

    /**
     * @return list<array<array-key, mixed>>
     */
    public function sections(): array
    {
        $sections = $this->page['sections'] ?? [];

        return is_array($sections) ? array_values(array_filter($sections, is_array(...))) : [];
    }

    /**
     * @return list<string>
     */
    public function sectionKeys(): array
    {
        return array_map(static fn (array $section): string => is_string($section['key'] ?? null) ? $section['key'] : '', $this->sections());
    }
}
