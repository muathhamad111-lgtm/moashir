<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

interface HtmlPdfPrinter
{
    public function print(string $html): string;
}
