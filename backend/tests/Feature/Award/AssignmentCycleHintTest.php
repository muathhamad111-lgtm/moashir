<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('tells the assign dialog that the cycle links no initial assessor, with the cycle only for its managers', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = submittedAdvancedRequest($cycle, $version);
    $path = "/api/v1/requests/assessors?assessment_id={$assessment->id}";

    switchTo(requestFollower())->getJson($path)->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.award_cycle.has_initial_assessors', false)
        ->assertJsonPath('meta.award_cycle.cycle_id', null);
    switchTo(systemAdmin())->getJson($path)->assertOk()
        ->assertJsonPath('meta.award_cycle.has_initial_assessors', false)
        ->assertJsonPath('meta.award_cycle.cycle_id', $cycle->id);

    linkInitialAssessor($cycle, assessorUser());
    switchTo(requestFollower())->getJson($path)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.award_cycle.has_initial_assessors', true);
});

it('adds no award hint to an accredited request', function (): void {
    $assessment = submittedAccreditedRequest(publishedAccreditedCriteria());

    switchTo(requestFollower())->getJson("/api/v1/requests/assessors?assessment_id={$assessment->id}")->assertOk()
        ->assertJsonPath('meta.award_cycle', null);
});
