<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Assessments\SubmittedScores;
use App\Data\Audit\AuditEntry;
use App\Data\Award\CommitteeScores;
use App\Enums\Audit\AuditEvent;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\ScoreInputParser;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeScoreReader;
use App\Services\Award\CommitteeScoreWriter;
use App\Services\Award\CommitteeWorkStarter;
use App\Services\Award\CommitteeWriteGate;
use Illuminate\Support\Facades\DB;

final readonly class SaveCommitteeScores
{
    public function __construct(
        private CommitteeWriteGate $gate,
        private CommitteeWorkStarter $starter,
        private ScoringTreeBuilder $trees,
        private ScoreInputParser $parser,
        private CommitteeScoreReader $reader,
        private CommitteeScoreWriter $writer,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, CommitteeAssignment $assignment, SubmittedScores $changes, ?string $reason): CommitteeScores
    {
        return DB::transaction(function () use ($actor, $assignment, $changes, $reason): CommitteeScores {
            $locked = $this->gate->enter($actor, $assignment, 'score_committee', $reason);
            $this->starter->begin($locked, $actor->id);
            $assessment = $locked->assessment;
            $tree = $this->trees->build($assessment->criteriaVersion);
            $given = $this->parser->parse($changes, $tree, $assessment->scale_kind);
            $desired = $this->reader->read($locked, $tree, $assessment->scale_kind)->merge($given, $this->parser->clearedKeys($changes, $tree));
            $recorded = $this->writer->replace($locked, $tree, $desired, $actor, $assessment->scale_kind);

            if (! $recorded->isEmpty()) {
                $locked->touch();
                $this->audit->record(new AuditEntry(AuditEvent::CommitteeScoresSaved, $locked, $recorded->before(), $recorded->after()));
            }

            return new CommitteeScores($locked, $tree, $desired);
        });
    }
}
