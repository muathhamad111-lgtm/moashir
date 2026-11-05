<?php

declare(strict_types=1);

namespace App\Data\Mail;

final readonly class RenderedMail
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $browserHtml,
        public string $text,
        public int $inlineImages,
    ) {}
}
