<?php

declare(strict_types=1);

namespace App\Data\Cms;

final readonly class LandingInstallation
{
    /**
     * @param  list<string>  $replacedLinks
     */
    private function __construct(public bool $isInstalled, public int $sections, public int $publishedSections, public array $replacedLinks) {}

    public static function unchanged(): self
    {
        return new self(false, 0, 0, []);
    }

    /**
     * @param  list<string>  $replacedLinks
     */
    public static function installed(int $sections, int $publishedSections, array $replacedLinks): self
    {
        return new self(true, $sections, $publishedSections, $replacedLinks);
    }

    public function summary(): string
    {
        return sprintf(
            '%d sections created, %d published; links replaced: %s.',
            $this->sections,
            $this->publishedSections,
            $this->replacedLinks === [] ? 'none (they were customised)' : implode(', ', $this->replacedLinks),
        );
    }
}
