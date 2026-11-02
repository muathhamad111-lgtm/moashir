<?php

declare(strict_types=1);

use App\Data\Award\CommitteeTally;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Award\CommitteeAssignment;
use App\Services\Award\CommitteeAverageCalculator;
use App\Support\ValueObjects\Percent;
use Illuminate\Support\Facades\Date;

function committeeRow(int $id, CommitteeAssignmentStatus $status, ?int $replaces = null, bool $approved = false): CommitteeAssignment
{
    $row = new CommitteeAssignment;
    $row->forceFill(['id' => $id, 'status' => $status, 'replaces_assignment_id' => $replaces, 'substitute_approved_at' => $approved ? Date::now() : null]);

    return $row;
}

it('averages the committee totals in fixed point with half up rounding', function (array $percents, string $expected): void {
    $average = new CommitteeAverageCalculator()->average(array_map(Percent::of(...), $percents));

    expect($average->toDecimal())->toBe($expected);
})->with([
    'one assessor' => [['77.0000'], '77.0000'],
    'two assessors' => [['77.0000', '100.0000'], '88.5000'],
    'three assessors with a repeating quotient' => [['70.0000', '80.0000', '80.0000'], '76.6667'],
    'half up on the last digit' => [['0.0001', '0.0002'], '0.0002'],
]);

it('refuses an average without any submitted evaluation', function (): void {
    expect(fn (): Percent => new CommitteeAverageCalculator()->average([]))->toThrow(InvalidArgumentException::class);
});

it('counts every live evaluation except a substitute awaiting approval', function (): void {
    $tally = new CommitteeTally(
        [committeeRow(1, CommitteeAssignmentStatus::Completed), committeeRow(2, CommitteeAssignmentStatus::InProgress), committeeRow(4, CommitteeAssignmentStatus::Completed, 3, true)],
        [committeeRow(5, CommitteeAssignmentStatus::Completed, 2)],
    );

    expect($tally->isComplete())->toBeFalse()
        ->and($tally->pendingIds())->toBe([2])
        ->and($tally->completedCount())->toBe(2)
        ->and($tally->pendingSubstituteIds())->toBe([5])
        ->and(new CommitteeTally([], [])->isComplete())->toBeFalse()
        ->and(new CommitteeTally([committeeRow(1, CommitteeAssignmentStatus::Completed)], [])->isComplete())->toBeTrue()
        ->and(committeeRow(9, CommitteeAssignmentStatus::NotStarted, 8)->isPendingSubstitute())->toBeTrue()
        ->and(committeeRow(9, CommitteeAssignmentStatus::NotStarted, 8, true)->isPendingSubstitute())->toBeFalse();
});
