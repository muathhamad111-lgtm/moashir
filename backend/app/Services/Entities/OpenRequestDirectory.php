<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\OpenRequestSource;
use App\Data\Entities\OpenEntityRequest;
use App\Models\Entities\Entity;
use Illuminate\Container\Attributes\Tag;

final readonly class OpenRequestDirectory
{
    public const string TAG = 'entities.open-request-sources';

    /**
     * @var list<OpenRequestSource>
     */
    private array $sources;

    /**
     * @param  iterable<OpenRequestSource>  $sources
     */
    public function __construct(#[Tag(self::TAG)] iterable $sources)
    {
        $this->sources = iterator_to_array($sources, false);
    }

    /**
     * @return list<OpenEntityRequest>
     */
    public function openRequestsOf(Entity $entity): array
    {
        $requests = [];

        foreach ($this->sources as $source) {
            array_push($requests, ...$source->openRequestsOf($entity));
        }

        $requests = array_map(static fn (OpenEntityRequest $request): OpenEntityRequest => $request->frozenWith($entity->trashed()), $requests);

        usort($requests, static fn (OpenEntityRequest $left, OpenEntityRequest $right): int => strcmp($left->number, $right->number));

        return $requests;
    }
}
