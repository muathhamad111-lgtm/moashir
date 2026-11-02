<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\CommitteeNote;

final readonly class CommitteeNotes
{
    /**
     * @param  array<int, CommitteeNote>  $subs
     */
    public function __construct(public array $subs, public ?CommitteeNote $general) {}

    /**
     * @return array{subs: object, general: string|null}
     */
    public function toArray(): array
    {
        $subs = [];

        foreach ($this->subs as $subId => $note) {
            $subs[(string) $subId] = $note->body;
        }

        return ['subs' => (object) $subs, 'general' => $this->general?->body];
    }
}
