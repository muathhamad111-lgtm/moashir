<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\GapClass;

final readonly class ClassifiedMain
{
    public function __construct(public ResultMain $main, public GapClass $class) {}
}
