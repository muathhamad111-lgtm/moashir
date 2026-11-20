<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Files\SignedDownload;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;

final readonly class IssueEvidenceBundleDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, EvidenceBundle $bundle): SignedDownload
    {
        if (! $bundle->isRequestedBy($actor->id)) {
            throw (new ModelNotFoundException)->setModel(EvidenceBundle::class);
        }

        $file = $bundle->loadMissing('storedFile')->storedFile;

        if (! $bundle->isDownloadable(Date::now()) || ! $file instanceof StoredFile) {
            throw AssessmentRejected::bundleNotReady();
        }

        return $this->downloads->issue($file, $actor);
    }
}
