<?php

declare(strict_types=1);

namespace App\Contracts\Watermark;

interface PdfOverlay
{
    /**
     * @param  list<list<int>>  $pagesBySheet  the source pages that take each stamp sheet, in sheet order
     */
    public function overlay(string $source, string $stamp, array $pagesBySheet, string $output): void;
}
