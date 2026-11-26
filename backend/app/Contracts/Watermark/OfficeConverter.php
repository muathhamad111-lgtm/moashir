<?php

declare(strict_types=1);

namespace App\Contracts\Watermark;

interface OfficeConverter
{
    public function toPdf(string $source, string $outputDirectory, string $profileDirectory): string;
}
