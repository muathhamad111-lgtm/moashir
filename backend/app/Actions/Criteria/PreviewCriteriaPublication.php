<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Contracts\Criteria\OpenAssessmentCounter;
use App\Data\Criteria\CriteriaPublicationPreview;
use App\Enums\Criteria\CriteriaNodeType;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaTreeLoader;
use App\Services\Criteria\CriteriaVersionDiff;

final readonly class PreviewCriteriaPublication
{
    public function __construct(
        private CriteriaTreeLoader $loader,
        private CriteriaVersionDiff $diff,
        private OpenAssessmentCounter $openAssessments,
    ) {}

    public function handle(CriteriaVersion $draft): CriteriaPublicationPreview
    {
        if (! $draft->isDraft()) {
            throw CriteriaVersionRejected::notDraft($draft->status);
        }

        $published = CriteriaVersion::query()->published()->first();

        if (! $published instanceof CriteriaVersion) {
            return new CriteriaPublicationPreview(null, [], [], 0);
        }

        $before = $this->loader->load($published);
        $after = $this->loader->load($draft);

        return new CriteriaPublicationPreview(
            $published,
            $this->diff->between($before, $after),
            [...$this->labelsOf($before), ...$this->labelsOf($after)],
            $this->openAssessments->countBoundTo($published),
        );
    }

    /**
     * @return array<string, array{ar: string, en: string}|null>
     */
    private function labelsOf(CriteriaVersion $version): array
    {
        $labels = [];

        foreach ($version->mainCriteria as $main) {
            $labels[CriteriaNodeType::Main->value.':'.$main->code] = $main->name->toArray();

            foreach ($main->subCriteria as $sub) {
                $labels[CriteriaNodeType::Sub->value.':'.$sub->code] = $sub->name->toArray();

                foreach ($sub->guidingPoints as $point) {
                    $labels[CriteriaNodeType::Point->value.':'.$point->code] = $point->description->toArray();
                }
            }
        }

        return $labels;
    }
}
