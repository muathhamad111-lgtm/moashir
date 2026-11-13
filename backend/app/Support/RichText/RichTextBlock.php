<?php

declare(strict_types=1);

namespace App\Support\RichText;

interface RichTextBlock
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
