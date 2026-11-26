<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

it('limits the secretary, filtering and committee boards to the cycles and seats of the user', function (): void {
    $scenario = committeeScenario(1, 2);
    [$first, $second] = $scenario['assessors'];
    submitCommitteeEvaluation($first, $scenario['requests'][0], $scenario['version'])->assertOk();

    $secretary = boardOf($scenario['secretary'], 'award_secretary');
    expect(boardCards($secretary)->all())->toMatchArray([
        'applicants' => 1, 'qualified' => 1, 'in_committee' => 1, 'awaiting_approval' => 0, 'committee_progress' => '50.00',
    ])->and(collect(boardWidget($secretary, 'cycles'))->pluck('cycle_id')->all())->toBe([$scenario['cycle']->id]);

    expect(boardCards(boardOf($scenario['officer'], 'filtering_officer'))->all())->toMatchArray(['awaiting_filtering' => 0, 'qualified' => 1, 'not_qualified' => 0]);

    $firstBoard = boardOf($first, 'committee_assessor');
    expect(boardCards($firstBoard)->all())->toMatchArray(['assigned' => 1, 'completed' => 1, 'remaining' => 0, 'progress' => '100.00'])
        ->and(boardCards($firstBoard)->get('own_average'))->toBe('77.00')
        ->and(boardWidget($firstBoard, 'latest_tasks')[0])->not->toHaveKeys(['entity', 'entity_name', 'average_percent'])
        ->and(boardCards(boardOf($second, 'committee_assessor'))->all())->toMatchArray(['assigned' => 1, 'completed' => 0, 'remaining' => 1]);

    $outsider = boardOf(awardSecretary(), 'award_secretary');
    expect(boardCards($outsider)->all())->toMatchArray(['applicants' => 0, 'qualified' => 0, 'committee_progress' => null])
        ->and(boardWidget($outsider, 'top_entities'))->toBe([])
        ->and(boardWidget($outsider, 'cycles'))->toBe([]);
});

it('ranks approved committee results and bins them on the secretary board', function (): void {
    $scenario = committeeScenario(1, 1);
    approvedByCommittee($scenario, $scenario['requests'][0]);

    $response = boardOf($scenario['secretary'], 'award_secretary');

    expect(boardCards($response)->get('results_approved'))->toBe(1)
        ->and(boardWidget($response, 'top_entities')[0] ?? null)->toMatchArray(['assessment_id' => $scenario['requests'][0]->id, 'average_percent' => '77.00', 'status' => 'approved'])
        ->and(collect(boardWidget($response, 'score_histogram'))->pluck('count', 'key')->get('from_70_to_79'))->toBe(1)
        ->and(collect(boardWidget($response, 'by_region'))->sum('count'))->toBe(1);
});
