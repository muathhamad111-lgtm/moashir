<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\TrendBucket;

final readonly class TrendSeries
{
    /**
     * @param  list<string>  $buckets  ISO dates of each bucket's first day
     * @param  array<string, list<int|string>>  $series  one value per bucket, keyed by series
     */
    public function __construct(
        public TrendBucket $bucket,
        public array $buckets,
        public array $series,
    ) {}
}
