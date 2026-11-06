<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Notification;

const INSIGHTS_SETTINGS = '/api/v1/settings/insights';

const INSIGHTS_REASON = 'معايرة حدود التصنيف بعد مراجعة لجنة الجودة للنتائج.';

beforeEach(function (): void {
    Notification::fake();
});

it('shows the default thresholds and bands with their versions', function (): void {
    signIn(systemAdmin());

    $this->getJson(INSIGHTS_SETTINGS)->assertOk()
        ->assertJsonPath('data.gap_thresholds', ['critical_below' => '50.00', 'strength_from' => '80.00', 'version' => 1])
        ->assertJsonPath('data.maturity_bands', ['developing_from' => '50.00', 'mature_from' => '70.00', 'leading_from' => '80.00', 'version' => 1])
        ->assertJsonPath('data.aligned', true)
        ->assertJsonPath('data.effective_since', null);
});

it('increments the server-managed version on a real change only and audits before and after', function (): void {
    signIn(systemAdmin());

    $this->putJson(INSIGHTS_SETTINGS, ['gap_thresholds' => ['critical_below' => '45.5', 'strength_from' => '85.00'], 'version' => 9])->assertOk()
        ->assertJsonPath('data.gap_thresholds', ['critical_below' => '45.50', 'strength_from' => '85.00', 'version' => 2])
        ->assertJsonPath('data.maturity_bands.version', 1)
        ->assertJsonPath('data.aligned', false);
    $this->putJson(INSIGHTS_SETTINGS, ['gap_thresholds' => ['critical_below' => '45.50', 'strength_from' => '85.00']])->assertOk()
        ->assertJsonPath('data.gap_thresholds.version', 2);
    $this->putJson(INSIGHTS_SETTINGS, ['maturity_bands' => ['developing_from' => '40.00', 'mature_from' => '60.00', 'leading_from' => '90.00']])->assertOk()
        ->assertJsonPath('data.maturity_bands.version', 2);

    $audits = Audit::query()->where('event', AuditEvent::InsightsSettingsUpdated->value)->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->old_values['insights.gap_thresholds'])->toEqual(['critical_below' => '50.00', 'strength_from' => '80.00', 'version' => 1])
        ->and($audits[0]->new_values['insights.gap_thresholds'])->toEqual(['critical_below' => '45.50', 'strength_from' => '85.00', 'version' => 2]);
});

it('shows the new rules version in the tools right after a change', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), ['M1.1' => '55.00', 'M1.2' => '55.00', 'M2.1' => '55.00', 'M2.2' => '55.00']);
    signIn(systemAdmin());
    $this->putJson(INSIGHTS_SETTINGS, ['gap_thresholds' => ['critical_below' => '60.00', 'strength_from' => '80.00']])->assertOk();

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/insights/strengths-gaps")->assertOk()
        ->assertJsonPath('data.rules.version', 2)
        ->assertJsonPath('data.counts.critical_gap', 4);
    switchTo(ownerOf($assessment->entity))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.rules.gap_thresholds_version', 2);
});

it('rejects thresholds that are not ascending, out of range or with three decimals', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $this->putJson(INSIGHTS_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Audit::query()->where('event', AuditEvent::InsightsSettingsUpdated->value)->exists())->toBeFalse();
})->with([
    'gaps not ascending' => [['gap_thresholds' => ['critical_below' => '80.00', 'strength_from' => '80.00']], 'gap_thresholds'],
    'bands not ascending' => [['maturity_bands' => ['developing_from' => '50.00', 'mature_from' => '40.00', 'leading_from' => '80.00']], 'maturity_bands'],
    'three decimals' => [['gap_thresholds' => ['critical_below' => '50.005', 'strength_from' => '80.00']], 'gap_thresholds.critical_below'],
    'above 100' => [['maturity_bands' => ['developing_from' => '50.00', 'mature_from' => '70.00', 'leading_from' => '100.01']], 'maturity_bands.leading_from'],
    'zero' => [['gap_thresholds' => ['critical_below' => '0', 'strength_from' => '80.00']], 'gap_thresholds.critical_below'],
    'missing value' => [['gap_thresholds' => ['critical_below' => '50.00']], 'gap_thresholds.strength_from'],
    'unknown key' => [['gap_thresholds' => ['critical_below' => '50.00', 'strength_from' => '80.00', 'version' => 5]], 'gap_thresholds'],
]);

it('requires a reason from the Super Admin', function (): void {
    signIn(superAdmin());

    $this->putJson(INSIGHTS_SETTINGS, ['gap_thresholds' => ['critical_below' => '40.00', 'strength_from' => '80.00']])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->putJson(INSIGHTS_SETTINGS, ['gap_thresholds' => ['critical_below' => '40.00', 'strength_from' => '80.00'], 'reason' => INSIGHTS_REASON])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::InsightsSettingsUpdated->value)->sole()->reason)->toBe(INSIGHTS_REASON);
});

it('denies the settings to roles without the assessment settings permission', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(INSIGHTS_SETTINGS)->assertForbidden();
    $this->putJson(INSIGHTS_SETTINGS, ['gap_thresholds' => ['critical_below' => '40.00', 'strength_from' => '80.00']])->assertForbidden();
})->with([RoleKey::ContentOfficer, RoleKey::FinanceOfficer, RoleKey::RequestFollower, RoleKey::Assessor]);
