<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Contracts\Files\PdfPasswordDetector;
use App\Data\Files\UploadCandidate;
use App\Exceptions\Files\FileRejected;
use App\Exceptions\Files\PdfPasswordCheckUnavailable;
use App\Support\Files\ContentSignature;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * Decides whether uploaded content may be stored: an allowed type whose bytes match it and, for a PDF, one that
 * opens without a password (a password-locked PDF can be neither reviewed nor watermarked).
 */
final readonly class UploadContentCheck
{
    private const int SIGNATURE_BYTES = 16;

    private const string PDF_MIME_TYPE = 'application/pdf';

    public function __construct(private PdfPasswordDetector $pdfPasswords, private ExceptionHandler $exceptions) {}

    /**
     * @param  array<string, list<string>>  $allowedTypes
     */
    public function assertAcceptable(UploadCandidate $candidate, array $allowedTypes, string $field): void
    {
        if (! $this->matchesTypes($candidate, $allowedTypes)) {
            throw FileRejected::contentMismatch($field);
        }

        if ($candidate->mimeType === self::PDF_MIME_TYPE && $this->needsPassword($candidate, $field)) {
            throw FileRejected::passwordProtected($field);
        }
    }

    /**
     * @param  array<string, list<string>>  $allowedTypes
     */
    private function matchesTypes(UploadCandidate $candidate, array $allowedTypes): bool
    {
        return array_key_exists($candidate->extension, $allowedTypes)
            && in_array($candidate->mimeType, $allowedTypes[$candidate->extension], true)
            && ContentSignature::matches($candidate->mimeType, substr($candidate->contents, 0, self::SIGNATURE_BYTES));
    }

    private function needsPassword(UploadCandidate $candidate, string $field): bool
    {
        try {
            return $this->pdfPasswords->requiresPassword($candidate->path);
        } catch (PdfPasswordCheckUnavailable $unavailable) {
            $this->exceptions->report($unavailable);

            throw FileRejected::checkFailed($field);
        }
    }
}
