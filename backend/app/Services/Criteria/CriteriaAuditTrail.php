<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

final readonly class CriteriaAuditTrail
{
    public function __construct(private AuditRecorder $audit, private CriteriaNodeSnapshot $snapshot) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshotOf(MainCriterion|SubCriterion|GuidingPoint $node): array
    {
        return match (true) {
            $node instanceof MainCriterion => $this->snapshot->ofMain($node),
            $node instanceof SubCriterion => $this->snapshot->ofSub($node),
            default => $this->snapshot->ofPoint($node),
        };
    }

    public function created(AuditEvent $event, MainCriterion|SubCriterion|GuidingPoint $node): void
    {
        $this->record($event, $node, [], $this->snapshotOf($node));
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function changed(AuditEvent $event, MainCriterion|SubCriterion|GuidingPoint $node, array $before): void
    {
        $this->record($event, $node, $before, $this->snapshotOf($node));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(AuditEvent $event, Model $subject, array $before, array $after): void
    {
        $this->audit->record(new AuditEntry($event, $subject, $before, $after));
    }
}
