<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\CriteriaSeed;
use App\Data\Criteria\PublishInstruction;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\CriteriaPublication;
use App\Services\Criteria\CriteriaSeedSource;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class InstallCriteriaSeed
{
    public function __construct(
        private CriteriaSeedSource $source,
        private CriteriaNodeWriter $writer,
        private CriteriaTreeLoader $loader,
        private CriteriaConsistencyChecker $checker,
        private CriteriaPublication $publication,
    ) {}

    public function handle(string $path): ?CriteriaVersion
    {
        if (CriteriaVersion::withTrashed()->exists()) {
            return null;
        }

        $seed = $this->source->load($path);

        return DB::transaction(function () use ($seed): CriteriaVersion {
            $draft = CriteriaVersion::query()->create(['version_number' => 1, 'label' => $seed->label]);
            $this->writeTree($draft, $seed);

            $loaded = $this->loader->load($draft);

            if (! $this->checker->check($loaded)->isConsistent()) {
                throw new LogicException('The bundled criteria seed has inconsistent weights.');
            }

            return $this->publication->publish(null, $loaded, null, new PublishInstruction(null, null))->version;
        });
    }

    private function writeTree(CriteriaVersion $draft, CriteriaSeed $seed): void
    {
        foreach ($seed->mains as $seedMain) {
            $main = $this->writer->addMain($draft, $seedMain->attributes, true);

            foreach ($seedMain->subs as $seedSub) {
                $sub = $this->writer->addSub($main, $seedSub->attributes, true);

                foreach ($seedSub->points as $point) {
                    $this->writer->addPoint($sub, $point, true);
                }
            }
        }
    }
}
