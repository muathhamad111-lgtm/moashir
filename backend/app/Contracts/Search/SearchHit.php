<?php

declare(strict_types=1);

namespace App\Contracts\Search;

interface SearchHit
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
