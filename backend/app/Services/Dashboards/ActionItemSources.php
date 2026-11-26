<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Contracts\Dashboards\ActionItemSource;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\Tag;

final readonly class ActionItemSources
{
    public const string TAG = 'dashboards.action-items';

    /**
     * @var list<ActionItemSource>
     */
    private array $sources;

    /**
     * @param  iterable<ActionItemSource>  $sources
     */
    public function __construct(#[Tag(self::TAG)] iterable $sources)
    {
        $this->sources = iterator_to_array($sources, false);
    }

    /**
     * @return list<ActionItemSource>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->sources, static fn (ActionItemSource $source): bool => $source->isAvailableTo($user)));
    }
}
