<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Enums\Identity\TermsKind;
use App\Support\ValueObjects\LocalizedText;

final readonly class LegalDraft
{
    public function __construct(
        public TermsKind $kind,
        public string $version,
        public LocalizedText $title,
        public LocalizedText $body,
    ) {}
}
