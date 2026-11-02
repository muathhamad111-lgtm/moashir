<?php

declare(strict_types=1);

namespace App\Data\Search;

use App\Contracts\Search\SearchHit;
use App\Enums\Search\SearchResultType;

final readonly class SearchResultGroup
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public SearchResultType $type,
        public int $total,
        public array $hits,
    ) {}

    public static function nothingFound(SearchResultType $type): self
    {
        return new self($type, 0, []);
    }
}
