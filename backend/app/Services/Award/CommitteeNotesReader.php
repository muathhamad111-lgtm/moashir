<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CommitteeNotes;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeNote;

final class CommitteeNotesReader
{
    public function read(CommitteeAssignment $assignment): CommitteeNotes
    {
        $subs = [];
        $general = null;

        foreach (CommitteeNote::query()->where('committee_assignment_id', $assignment->id)->current()->orderBy('id')->get() as $note) {
            if ($note->isGeneral()) {
                $general = $note;

                continue;
            }

            $subs[(int) $note->sub_criterion_id] = $note;
        }

        return new CommitteeNotes($subs, $general);
    }
}
