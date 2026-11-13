<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Data\Files\ImageChange;
use App\Data\Kpis\KpiChanges;
use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Kpis\KpiRejected;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use App\Services\Files\PublicAssetAttacher;
use App\Services\Files\StoredFileRemover;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class KpiWriter
{
    private const string IMAGE_FIELD = 'image_file_id';

    public function __construct(
        private PublicAssetAttacher $assets,
        private StoredFileRemover $remover,
        private KpiSlugs $slugs,
        private Repository $config,
    ) {}

    public function write(Kpi $kpi, KpiChanges $changes, User $actor): void
    {
        $this->assertSlugFree($kpi, $changes->slug());
        $this->assertFeaturedRoom($kpi, $changes->featured());
        $image = $this->resolveImage($kpi, $changes->image);
        $previousImage = $kpi->image_file_id;
        $previousValue = $kpi->exists ? $kpi->value?->value : null;

        $kpi->fill($changes->attributes);
        $kpi->forceFill(['updated_by_user_id' => $actor->id]);

        if ($changes->changesValue() && $previousValue !== $kpi->value?->value) {
            $kpi->forceFill(['value_updated_at' => Date::now()]);
        }

        if ($changes->image instanceof ImageChange) {
            $kpi->forceFill([self::IMAGE_FIELD => $image?->id]);
        }

        $kpi->save();
        $this->finishImage($kpi, $image, $previousImage);
    }

    private function assertSlugFree(Kpi $kpi, ?string $slug): void
    {
        if ($slug !== null && $this->slugs->isTaken($slug, $kpi->exists ? $kpi->id : null)) {
            throw KpiRejected::slugTaken();
        }
    }

    private function assertFeaturedRoom(Kpi $kpi, ?bool $featured): void
    {
        if ($featured !== true || ($kpi->exists && $kpi->show_on_homepage)) {
            return;
        }

        $limit = $this->config->integer('moashir.kpis.featured_max');

        if (Kpi::query()->where('show_on_homepage', true)->count() >= $limit) {
            throw KpiRejected::featuredLimitReached($limit);
        }
    }

    private function resolveImage(Kpi $kpi, ?ImageChange $change): ?StoredFile
    {
        if (! $change instanceof ImageChange || $change->fileId === null) {
            return null;
        }

        return $this->assets->resolve($change->fileId, StoredFilePurpose::KpiImage, $kpi, self::IMAGE_FIELD);
    }

    private function finishImage(Kpi $kpi, ?StoredFile $image, ?int $previousImage): void
    {
        if ($image instanceof StoredFile) {
            $this->assets->attach($image, $kpi);
        }

        if ($previousImage !== null && $previousImage !== $kpi->image_file_id) {
            $previous = StoredFile::query()->find($previousImage);

            if ($previous instanceof StoredFile) {
                $this->remover->remove($previous);
            }
        }
    }
}
