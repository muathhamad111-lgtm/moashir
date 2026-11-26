<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function passStationUrl(int $assessmentId, int $mainId): string
{
    return "/api/v1/assessments/{$assessmentId}/stations/{$mainId}/passed";
}

it('serves no passed station on a fresh form and records each passed station once', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $leadership = mainOf($version, 'M1');

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.passed_stations', []);

    switchTo($owner)->putJson(passStationUrl($assessment->id, $leadership->id))->assertOk()
        ->assertJsonPath('data.passed_stations', [$leadership->id]);
    switchTo($owner)->putJson(passStationUrl($assessment->id, $leadership->id))->assertOk()
        ->assertJsonPath('data.passed_stations', [$leadership->id]);

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.passed_stations', [$leadership->id]);
    expect(DB::table('assessment_passed_stations')->where('assessment_id', $assessment->id)->count())->toBe(1);
});

it('keeps the missing items unchanged when a station is passed', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    $before = switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->json('data.missing');
    switchTo($owner)->putJson(passStationUrl($assessment->id, mainOf($version, 'M1')->id))->assertOk();

    expect(switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->json('data.missing'))->toBe($before);
});

it('rejects a main criterion outside the bound criteria version', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    switchTo(ownerOf($assessment->entity))->putJson(passStationUrl($assessment->id, 999_999))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'assessment.main_not_in_version')
        ->assertJsonValidationErrors(['main_criterion_id']);
});

it('keeps the passed stations to the owning entity', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $mainId = mainOf($version, 'M1')->id;

    switchTo(ownerOf(entityWithOwner()))->putJson(passStationUrl($assessment->id, $mainId))->assertNotFound();
    switchTo(requestFollower())->putJson(passStationUrl($assessment->id, $mainId))->assertForbidden();
    expect(DB::table('assessment_passed_stations')->count())->toBe(0);
});

it('refuses to record a station once the request is submitted', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);

    switchTo(ownerOf($assessment->entity))->putJson(passStationUrl($assessment->id, mainOf($version, 'M1')->id))
        ->assertStatus(409);
    expect(DB::table('assessment_passed_stations')->count())->toBe(0);
});
