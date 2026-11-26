<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Identity\Ability;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\Tag;

final readonly class OverviewBreakdowns
{
    public const string TAG = 'dashboards.overview-breakdowns';

    /**
     * @var list<OverviewBreakdownSource>
     */
    private array $sources;

    /**
     * @param  iterable<OverviewBreakdownSource>  $sources
     */
    public function __construct(#[Tag(self::TAG)] iterable $sources)
    {
        $this->sources = iterator_to_array($sources, false);
    }

    /**
     * @return array<string, mixed> the breakdowns the viewer may see, in the order of OverviewBreakdownKey
     */
    public function visibleTo(User $viewer, ReportingPeriod $period, BoardFilter $filter): array
    {
        $breakdowns = [];

        foreach ($this->sources as $source) {
            if ($this->canSee($viewer, $source->keys())) {
                $breakdowns += $source->breakdowns($period, $filter);
            }
        }

        $ordered = [];

        foreach (OverviewBreakdownKey::cases() as $key) {
            if (array_key_exists($key->value, $breakdowns)) {
                $ordered[$key->value] = $breakdowns[$key->value];
            }
        }

        return $ordered;
    }

    /**
     * @param  list<OverviewBreakdownKey>  $keys
     */
    private function canSee(User $viewer, array $keys): bool
    {
        foreach ($keys as $key) {
            $ability = $key->requiredAbility();

            if ($ability instanceof Ability && ! $viewer->can($ability->value)) {
                return false;
            }
        }

        return true;
    }
}
