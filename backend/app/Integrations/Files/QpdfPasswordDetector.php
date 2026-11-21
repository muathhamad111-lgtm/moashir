<?php

declare(strict_types=1);

namespace App\Integrations\Files;

use App\Contracts\Files\PdfPasswordDetector;
use App\Exceptions\Files\PdfPasswordCheckUnavailable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory as ProcessFactory;

/**
 * Exit codes of `qpdf --requires-password`: 0 needs a password to open, 3 is encrypted but opens without one
 * (owner restrictions only), 2 is not encrypted or not readable as a PDF.
 */
final readonly class QpdfPasswordDetector implements PdfPasswordDetector
{
    private const int NEEDS_PASSWORD = 0;

    private const array OPENS_WITHOUT_PASSWORD = [2, 3];

    public function __construct(private ProcessFactory $processes, private Repository $config) {}

    public function requiresPassword(string $path): bool
    {
        if (! is_file($path)) {
            throw PdfPasswordCheckUnavailable::missingFile();
        }

        try {
            $exitCode = $this->processes->newPendingProcess()
                ->timeout($this->config->integer('moashir.watermark.upload_check_timeout_seconds'))
                ->run([$this->config->string('moashir.watermark.qpdf_binary'), '--requires-password', $path])
                ->exitCode();
        } catch (ProcessTimedOutException) {
            throw PdfPasswordCheckUnavailable::timedOut();
        }

        if ($exitCode === self::NEEDS_PASSWORD) {
            return true;
        }

        if (in_array($exitCode, self::OPENS_WITHOUT_PASSWORD, true)) {
            return false;
        }

        throw PdfPasswordCheckUnavailable::exitedWith($exitCode);
    }
}
