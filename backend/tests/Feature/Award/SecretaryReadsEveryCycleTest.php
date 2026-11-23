<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\CommitteeAssignment;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

// Operator decision 2026-11-22: the Award Secretary role reads every award cycle, its committee roster, its secretary
// desk and its results; every action stays with the cycle's own Award Secretaries.

const OUTSIDER_DISCUSSION_OUTCOME = 'اتفقت اللجنة على اعتماد المتوسط كما هو مع التوصية بتحسين توثيق الشواهد.';

/** Keys that would carry personal or contact data; none may reach a secretary reading another cycle. */
const PERSONAL_DATA_KEYS = ['email', 'official_email', 'phone', 'mobile', 'national_id', 'contact', 'contact_email', 'contact_phone', 'authorized_person_name', 'address', 'iban'];

/**
 * A cycle in committee where every desk, roster and results route has a real target: an approved (ranked) request,
 * a request with an open discussion and a request with a substitute evaluation.
 *
 * @return array{cycle: AwardCycle, approved: int, discussed: int, discussion: int, substituted: int, substitution: int, seat: int, region: int}
 */
function outsiderTargets(): array
{
    $scenario = committeeScenario(3, 2);
    $cycle = $scenario['cycle'];
    [$approved, $discussed, $substituted] = $scenario['requests'];
    [$first, $second] = $scenario['assessors'];
    approvedByCommittee($scenario, $approved);
    submitCommitteeEvaluation($first, $discussed, $scenario['version'])->assertOk();
    submitCommitteeEvaluation($second, $discussed, $scenario['version'])->assertOk();
    $discussion = switchTo($scenario['secretary'])->postJson(secretaryPath($cycle, $discussed, 'discussions'), ['mode' => 'agreed_outcome', 'agreed_outcome' => OUTSIDER_DISCUSSION_OUTCOME])->assertCreated()->json('data.id');
    $replacedSeat = seatOf($cycle, $second);
    $substitute = assessorUser();
    test()->postJson(committeePath($cycle, "members/{$replacedSeat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertOk();
    test()->postJson(committeePath($cycle, "members/{$replacedSeat->id}/substitute"), ['substitute_user_id' => $substitute->id])->assertCreated();

    return [
        'cycle' => $cycle->refresh(),
        'approved' => $approved->id,
        'discussed' => $discussed->id,
        'discussion' => (int) $discussion,
        'substituted' => $substituted->id,
        'substitution' => committeeRowOf($substitute, $substituted)->id,
        'seat' => seatOf($cycle, $first)->id,
        'region' => $cycle->regions->firstOrFail()->id,
    ];
}

/**
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function keysIn(array $data): array
{
    $keys = [];

    foreach ($data as $key => $value) {
        $keys[] = (string) $key;

        if (is_array($value)) {
            $keys = [...$keys, ...keysIn($value)];
        }
    }

    return $keys;
}

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('lets an Award Secretary of another cycle read the cycle, its roster, desk and results', function (): void {
    ['cycle' => $cycle, 'requests' => [$assessment]] = committeeScenario();
    $outsider = awardSecretary();
    $base = "/api/v1/award/cycles/{$cycle->id}";

    $listed = array_column(switchTo($outsider)->getJson('/api/v1/award/cycles')->assertOk()->json('data'), 'id');
    expect($listed)->toContain($cycle->id);
    test()->getJson($base)->assertOk()->assertJsonPath('data.can_manage', false);
    test()->getJson("{$base}/committee")->assertOk()->assertJsonPath('meta.can_manage', false);
    test()->getJson(secretaryPath($cycle))->assertOk();
    test()->getJson(secretaryPath($cycle, $assessment))->assertOk()->assertJsonPath('meta.is_cycle_secretary', false);
    test()->getJson("{$base}/results")->assertOk()->assertJsonPath('meta.is_cycle_secretary', false);
    test()->getJson("{$base}/national")->assertOk()->assertJsonPath('meta.is_cycle_secretary', false);
});

it('keeps every action of a cycle with its own Award Secretaries', function (): void {
    ['cycle' => $cycle, 'secretary' => $member, 'requests' => [$assessment]] = committeeScenario();
    $outsider = awardSecretary();
    $base = "/api/v1/award/cycles/{$cycle->id}";

    switchTo($outsider)->putJson($base, ['year' => 2027])->assertForbidden();
    test()->postJson("{$base}/close-submissions")->assertForbidden();
    test()->postJson("{$base}/committee/members", ['user_id' => assessorUser()->id])->assertForbidden();
    test()->postJson(secretaryPath($cycle, $assessment, 'average'))->assertForbidden();
    test()->postJson(secretaryPath($cycle, $assessment, 'approve'))->assertForbidden();
    test()->postJson("{$base}/national/approve")->assertForbidden();
    test()->postJson("{$base}/announce")->assertForbidden();
    test()->getJson("{$base}/filtering")->assertForbidden();

    switchTo($member)->getJson("{$base}/committee")->assertOk()->assertJsonPath('meta.can_manage', true);
    test()->getJson(secretaryPath($cycle, $assessment))->assertOk()->assertJsonPath('meta.is_cycle_secretary', true);
    test()->getJson("{$base}/results")->assertOk()->assertJsonPath('meta.is_cycle_secretary', true);
});

it('still hides the cycles from staff outside the award roles', function (): void {
    ['cycle' => $cycle] = committeeScenario();

    expect(array_column(switchTo(assessorUser())->getJson('/api/v1/award/cycles')->assertOk()->json('data'), 'id'))->not->toContain($cycle->id);
    switchTo(filteringOfficer())->getJson("/api/v1/award/cycles/{$cycle->id}")->assertNotFound();
});

it('refuses every mutating desk, roster, results and cycle route to an Award Secretary of another cycle', function (): void {
    $t = outsiderTargets();
    $cycle = "/api/v1/award/cycles/{$t['cycle']->id}";
    $desk = "{$cycle}/secretary/requests";
    $routes = [
        ['PUT', $cycle], ['PUT', "{$cycle}/members"], ['PUT', "{$cycle}/initial-assessors"],
        ['POST', "{$cycle}/open"], ['POST', "{$cycle}/close-submissions"], ['POST', "{$cycle}/close"], ['POST', "{$cycle}/reopen"],
        ['POST', "{$cycle}/start-committee"], ['POST', "{$cycle}/announce"],
        ['POST', "{$cycle}/committee/members"], ['DELETE', "{$cycle}/committee/members/{$t['seat']}"],
        ['POST', "{$cycle}/committee/members/{$t['seat']}/disable"], ['POST', "{$cycle}/committee/members/{$t['seat']}/exclude"],
        ['POST', "{$cycle}/committee/members/{$t['seat']}/substitute"],
        ['POST', "{$desk}/{$t['discussed']}/average"], ['POST', "{$desk}/{$t['discussed']}/approve"], ['POST', "{$desk}/{$t['discussed']}/return"],
        ['POST', "{$desk}/{$t['substituted']}/substitutions/{$t['substitution']}/approve"],
        ['POST', "{$desk}/{$t['discussed']}/discussions"], ['POST', "{$desk}/{$t['discussed']}/discussions/{$t['discussion']}/close"],
        ['PUT', "{$cycle}/results/{$t['approved']}/regional-rank"], ['POST', "{$cycle}/regions/{$t['region']}/approve"],
        ['POST', "{$cycle}/regions/{$t['region']}/unapprove"], ['PUT', "{$cycle}/national/{$t['approved']}/rank"],
        ['POST', "{$cycle}/national/approve"],
    ];
    $statusBefore = $t['cycle']->status;
    $outsider = awardSecretary();

    foreach ($routes as [$method, $path]) {
        $this->travel(2)->minutes(); // past the per-user write throttle, so each call reaches the policy
        expect(switchTo($outsider)->json($method, $path, ['rank' => 1, 'reason' => COMMITTEE_DISABLE_REASON])->status())->toBe(403, "{$method} {$path}");
    }

    expect(AwardCycle::query()->findOrFail($t['cycle']->id)->status)->toBe($statusBefore)
        ->and(AwardRanking::query()->where('assessment_id', $t['approved'])->value('regional_rank'))->toBeNull();
});

it('shows an Award Secretary of another cycle no personal or contact data, whatever the committee visibility setting', function (): void {
    $t = outsiderTargets();
    app(SettingsStore::class)->put(SettingKey::CommitteeEntityDataVisibility, 'full', AuditEvent::AssessmentSettingsUpdated, systemAdmin());
    $cycle = "/api/v1/award/cycles/{$t['cycle']->id}";
    $desk = "{$cycle}/secretary/requests";
    $evaluation = CommitteeAssignment::query()->where('assessment_id', $t['discussed'])->value('id');
    $outsider = awardSecretary();
    $keys = [];

    foreach ([$cycle, "{$cycle}/committee", $desk, "{$desk}/{$t['discussed']}", "{$desk}/{$t['discussed']}/evaluations/{$evaluation}", "{$cycle}/results", "{$cycle}/national"] as $path) {
        $keys = [...$keys, ...keysIn((array) switchTo($outsider)->getJson($path)->assertOk()->json())];
    }

    expect(array_values(array_intersect(array_unique($keys), PERSONAL_DATA_KEYS)))->toBe([]);
    expect(array_keys((array) switchTo($outsider)->getJson("{$desk}/{$t['discussed']}")->json('data.entity')))->toBe(['id', 'reference', 'name', 'status']);
});
