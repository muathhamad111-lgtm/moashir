<?php

declare(strict_types=1);

namespace App\Data\Demo;

final readonly class DemoLegalTexts
{
    /**
     * @param  list<int>  $currentIds
     */
    public function __construct(public array $currentIds, public int $published) {}
}
