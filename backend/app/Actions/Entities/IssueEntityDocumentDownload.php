<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Files\SignedDownload;
use App\Enums\Entities\EntityDocument;
use App\Exceptions\Entities\EntityDocumentRejected;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;

final readonly class IssueEntityDocumentDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $viewer, Entity $entity, EntityDocument $document): SignedDownload
    {
        $fileId = $entity->getAttribute($document->column());
        $file = is_int($fileId) ? StoredFile::query()->find($fileId) : null;

        if (! $file instanceof StoredFile) {
            throw EntityDocumentRejected::missing();
        }

        return $this->downloads->issue($file, $viewer);
    }
}
