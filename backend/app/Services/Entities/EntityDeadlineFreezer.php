<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\FreezableDeadlineSource;
use App\Models\Entities\Entity;
use App\Models\Entities\FrozenDeadline;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

final readonly class EntityDeadlineFreezer
{
    public const string TAG = 'entities.freezable-deadline-sources';

    /**
     * @var list<FreezableDeadlineSource>
     */
    private array $sources;

    /**
     * @param  iterable<FreezableDeadlineSource>  $sources
     */
    public function __construct(#[Tag(self::TAG)] iterable $sources)
    {
        $this->sources = iterator_to_array($sources, false);
    }

    public function pause(Entity $entity): int
    {
        $frozenAt = Date::now();
        $paused = 0;

        foreach ($this->sources as $source) {
            foreach ($source->runningDeadlinesOf($entity) as $deadline) {
                FrozenDeadline::query()->create([
                    'entity_id' => $entity->id,
                    'subject_type' => $deadline->subject->getMorphClass(),
                    'subject_id' => $deadline->subject->getKey(),
                    'attribute' => $deadline->attribute,
                    'due_at' => $deadline->dueAt,
                    'remaining_seconds' => $deadline->dueAt->getTimestamp() - $frozenAt->getTimestamp(),
                    'frozen_at' => $frozenAt,
                ]);
                $paused++;
            }
        }

        return $paused;
    }

    public function resume(Entity $entity): int
    {
        $resumedAt = Date::now();
        $frozen = FrozenDeadline::query()->where('entity_id', $entity->id)->with('subject')->orderBy('id')->get();

        foreach ($frozen as $deadline) {
            if ($deadline->subject instanceof Model) {
                $deadline->subject->forceFill([$deadline->attribute => $resumedAt->copy()->addSeconds($deadline->remaining_seconds)])->save();
            }

            $deadline->delete();
        }

        return $frozen->count();
    }

    public function shift(Model $subject, string $attribute, int $seconds): int
    {
        $shifted = 0;
        $frozen = FrozenDeadline::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('attribute', $attribute)
            ->get();

        foreach ($frozen as $deadline) {
            $deadline->forceFill([
                'due_at' => $deadline->due_at->copy()->addSeconds($seconds),
                'remaining_seconds' => $deadline->remaining_seconds + $seconds,
            ])->save();
            $shifted++;
        }

        return $shifted;
    }

    public function forget(Entity $entity): void
    {
        FrozenDeadline::query()->where('entity_id', $entity->id)->delete();
    }
}
