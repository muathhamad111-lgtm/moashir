<?php

declare(strict_types=1);

namespace App\Contracts\Files;

use App\Data\Files\ScanVerdict;

interface FileScanner
{
    /**
     * @param  resource  $stream
     */
    public function scan($stream): ScanVerdict;
}
