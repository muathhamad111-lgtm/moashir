<?php

declare(strict_types=1);

namespace App\Data\Award;

final readonly class CommitteeNoteChanges
{
    /**
     * @param  array<int, string|null>  $subs
     */
    public function __construct(public array $subs, public bool $touchesGeneral, public ?string $general) {}
}
