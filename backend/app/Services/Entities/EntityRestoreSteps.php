<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\EntityRestoreStep;
use App\Models\Entities\Entity;
use Illuminate\Container\Attributes\Tag;

final readonly class EntityRestoreSteps
{
    public const string TAG = 'entities.restore-steps';

    /**
     * @var list<EntityRestoreStep>
     */
    private array $steps;

    /**
     * @param  iterable<EntityRestoreStep>  $steps
     */
    public function __construct(#[Tag(self::TAG)] iterable $steps)
    {
        $this->steps = iterator_to_array($steps, false);
    }

    public function runFor(Entity $entity): void
    {
        foreach ($this->steps as $step) {
            $step->onRestore($entity);
        }
    }
}
