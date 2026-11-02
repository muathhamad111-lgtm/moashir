<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\PublishedCriteriaTree;

final readonly class GetPublishedCriteriaTree
{
    public function __construct(private PublishedCriteriaTree $tree) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?CriteriaVersion $version): array
    {
        if ($version instanceof CriteriaVersion) {
            return $version->isDraft() ? throw CriteriaVersionRejected::noPublishedVersion() : $this->tree->of($version);
        }

        return $this->tree->current() ?? throw CriteriaVersionRejected::noPublishedVersion();
    }
}
