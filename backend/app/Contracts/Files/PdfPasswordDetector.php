<?php

declare(strict_types=1);

namespace App\Contracts\Files;

use App\Exceptions\Files\PdfPasswordCheckUnavailable;

interface PdfPasswordDetector
{
    /**
     * True when the PDF cannot be opened without a password. A PDF that only restricts printing or copying opens
     * without one, and a damaged PDF is left to the other checks.
     *
     * @throws PdfPasswordCheckUnavailable
     */
    public function requiresPassword(string $path): bool;
}
