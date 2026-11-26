<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\EntityDocument;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * The documents an entity uploads with its registration, keyed by document.
 */
final readonly class RegistrationDocuments
{
    /**
     * @param  array<string, UploadedFile>  $uploads
     */
    public function __construct(private array $uploads) {}

    public function uploadOf(EntityDocument $document): UploadedFile
    {
        return $this->uploads[$document->value] ?? throw new LogicException('Every registration document is validated before it is taken in.');
    }
}
