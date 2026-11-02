<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Files\SignedDownload;
use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class IssueEvidenceDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, EvidenceFile $evidence): SignedDownload
    {
        if (! $evidence->isPresent()) {
            throw (new ModelNotFoundException)->setModel(EvidenceFile::class);
        }

        return $this->downloads->issue($evidence->loadMissing('storedFile')->storedFile, $actor);
    }
}
