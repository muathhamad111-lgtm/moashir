<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CommitteeNoteChanges;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeNote;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final readonly class CommitteeNotesWriter
{
    public function __construct(private CommitteeNotesReader $reader) {}

    /**
     * @return list<string>
     */
    public function apply(CommitteeAssignment $assignment, CommitteeNoteChanges $changes, User $author): array
    {
        $current = $this->reader->read($assignment);
        $changed = [];

        foreach ($changes->subs as $subId => $body) {
            if ($this->write($assignment, $current->subs[$subId] ?? null, $subId, $body, $author)) {
                $changed[] = 'sub:'.$subId;
            }
        }

        if ($changes->touchesGeneral && $this->write($assignment, $current->general, null, $changes->general, $author)) {
            $changed[] = 'general';
        }

        return $changed;
    }

    private function write(CommitteeAssignment $assignment, ?CommitteeNote $current, ?int $subId, ?string $body, User $author): bool
    {
        if ($current?->body === $body) {
            return false;
        }

        if (! $current instanceof CommitteeNote && $body === null) {
            return false;
        }

        $current?->forceFill(['superseded_at' => Date::now()])->save();

        if ($body !== null) {
            CommitteeNote::query()->create([
                'committee_assignment_id' => $assignment->id,
                'sub_criterion_id' => $subId,
                'body' => $body,
                'version' => $this->nextVersion($assignment, $subId),
                'author_user_id' => $author->id,
            ]);
        }

        return true;
    }

    private function nextVersion(CommitteeAssignment $assignment, ?int $subId): int
    {
        $latest = CommitteeNote::query()
            ->where('committee_assignment_id', $assignment->id)
            ->when($subId === null, static fn (Builder $query): Builder => $query->whereNull('sub_criterion_id'), static fn (Builder $query): Builder => $query->where('sub_criterion_id', $subId))
            ->max('version');

        return ScalarValue::int($latest) + 1;
    }
}
