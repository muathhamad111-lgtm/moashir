<?php

declare(strict_types=1);

use App\Data\Dashboards\HeldBoards;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Dashboards\DashboardBoard;
use App\Enums\Identity\RoleKey;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\Ratio;

it('computes percentages in integer arithmetic, half up, with two decimals', function (int $part, int $whole, ?string $expected): void {
    expect(Ratio::percent($part, $whole))->toBe($expected);
})->with([
    [1, 3, '33.33'],
    [2, 3, '66.67'],
    [1, 8, '12.50'],
    [5, 5, '100.00'],
    [0, 4, '0.00'],
    [3, 0, null],
]);

it('orders the boards by the landing precedence of the requirements', function (): void {
    $order = [
        DashboardBoard::SystemAdmin, DashboardBoard::AwardSecretary, DashboardBoard::FilteringOfficer,
        DashboardBoard::RequestFollower, DashboardBoard::Assessor, DashboardBoard::CommitteeAssessor,
        DashboardBoard::ContentOfficer, DashboardBoard::CriteriaOfficer, DashboardBoard::FinanceOfficer,
        DashboardBoard::Entity, DashboardBoard::Executive,
    ];

    $precedences = array_map(static fn (DashboardBoard $board): int => $board->precedence(), $order);

    expect($precedences)->toBe(range(0, count($order) - 1));

    foreach (RoleKey::cases() as $role) {
        expect(DashboardBoard::ofRole($role)->value)->toBe($role->value);
    }
});

it('ranks danger before warning before info', function (): void {
    expect(ActionSeverity::Danger->rank())->toBeLessThan(ActionSeverity::Warning->rank())
        ->and(ActionSeverity::Warning->rank())->toBeLessThan(ActionSeverity::Info->rank());
});

it('builds one placeholder per bound value', function (): void {
    expect(AggregateRow::placeholders(['a']))->toBe('?')
        ->and(AggregateRow::placeholders(['a', 'b', 'c']))->toBe('?, ?, ?');
});

it('tells whether a board is held', function (): void {
    $held = new HeldBoards([DashboardBoard::RequestFollower, DashboardBoard::Assessor], DashboardBoard::RequestFollower);

    expect($held->holds(DashboardBoard::Assessor))->toBeTrue()
        ->and($held->holds(DashboardBoard::SystemAdmin))->toBeFalse();
});
