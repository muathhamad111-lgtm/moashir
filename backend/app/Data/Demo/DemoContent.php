<?php

declare(strict_types=1);

namespace App\Data\Demo;

final readonly class DemoContent
{
    public function __construct(
        public int $publishedPages,
        public int $kpis,
        public int $legalTexts,
        public int $legalPages,
    ) {}

    public function summary(): string
    {
        return sprintf(
            '%d published content pages and %d published KPIs; %d legal texts and %d legal pages published from the legal drafts',
            $this->publishedPages,
            $this->kpis,
            $this->legalTexts,
            $this->legalPages,
        );
    }
}
