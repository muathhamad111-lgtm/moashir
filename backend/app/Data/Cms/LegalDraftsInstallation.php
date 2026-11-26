<?php

declare(strict_types=1);

namespace App\Data\Cms;

final readonly class LegalDraftsInstallation
{
    public function __construct(public int $textDrafts, public int $pageDrafts) {}

    public function summary(): string
    {
        return sprintf('%d unpublished legal text draft(s) and %d unpublished legal page(s) stored', $this->textDrafts, $this->pageDrafts);
    }
}
