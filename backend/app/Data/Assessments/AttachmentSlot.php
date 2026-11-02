<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class AttachmentSlot
{
    public function __construct(public string $key, public bool $isRequired) {}
}
