<?php

declare(strict_types=1);

namespace App\Data\Entities;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class FreezableDeadline
{
    public function __construct(public Model $subject, public string $attribute, public CarbonInterface $dueAt) {}
}
