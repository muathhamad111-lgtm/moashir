<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\LocalizedText;

final readonly class CriteriaSeed
{
    /**
     * @param  list<CriteriaSeedMain>  $mains
     */
    public function __construct(public LocalizedText $label, public array $mains) {}
}
