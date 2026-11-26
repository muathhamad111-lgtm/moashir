<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Support\ValueObjects\LocalizedText;

final readonly class CycleDescriptionChange
{
    public function __construct(public ?LocalizedText $text) {}
}
