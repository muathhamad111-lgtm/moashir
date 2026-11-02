<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Data\Identity\StaffActor;

final readonly class StaffProfileChangeEntry
{
    public function __construct(public ProfileChangeEntry $entry, public ?StaffActor $author) {}
}
