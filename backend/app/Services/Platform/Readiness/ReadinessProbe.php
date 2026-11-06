<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Contracts\Platform\ReadinessCheck;
use App\Data\Platform\ReadinessReport;
use App\Data\Platform\ReadinessResult;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Tag;

final readonly class ReadinessProbe
{
    public const string TAG = 'platform.readiness-checks';

    /**
     * @var list<ReadinessCheck>
     */
    private array $checks;

    /**
     * @param  iterable<ReadinessCheck>  $checks
     */
    public function __construct(#[Tag(self::TAG)] iterable $checks)
    {
        $this->checks = iterator_to_array($checks, false);
    }

    public function run(bool $isDetailed): ReadinessReport
    {
        return new ReadinessReport(
            array_map(static fn (ReadinessCheck $check): ReadinessResult => new ReadinessResult($check->name(), $check->isCritical(), $check->status()), $this->checks),
            CarbonImmutable::now(),
            $isDetailed,
        );
    }
}
