<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Support\ValueObjects\LocalizedText;

final readonly class LegalDocumentText
{
    public function __construct(public LocalizedText $title, public LocalizedText $body) {}
}
