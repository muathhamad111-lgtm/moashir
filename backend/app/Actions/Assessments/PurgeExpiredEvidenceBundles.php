<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\EvidenceBundleStatus;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileRemover;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class PurgeExpiredEvidenceBundles
{
    private const int CHUNK = 100;

    public function __construct(private StoredFileRemover $remover) {}

    public function handle(): int
    {
        $purged = 0;

        EvidenceBundle::query()
            ->with('storedFile')
            ->where('status', EvidenceBundleStatus::Ready)
            ->where('expires_at', '<=', Date::now())
            ->chunkById(self::CHUNK, function (Collection $bundles) use (&$purged): void {
                foreach ($bundles as $bundle) {
                    if ($bundle->storedFile instanceof StoredFile) {
                        $this->remover->remove($bundle->storedFile);
                    }

                    $bundle->forceFill(['status' => EvidenceBundleStatus::Expired])->save();
                    $purged++;
                }
            });

        return $purged;
    }
}
