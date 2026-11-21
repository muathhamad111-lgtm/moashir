<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\ScoringType;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function pointTypePath(SubCriterion $sub): string
{
    return '/api/v1/criteria/subs/'.$sub->id.'/points/scoring-type';
}

it('gives every indicator of a sub-criterion the chosen type and makes it the default for new ones', function (): void {
    $draft = balancedDraft();
    $sub = subOf($draft, 'M1.1');
    pointOf($draft, 'M1.1.2')->forceFill(['scoring_type' => ScoringType::Achievement])->save();
    $actor = criteriaOfficer();

    switchTo($actor)->putJson(pointTypePath($sub), ['scoring_type' => 'achievement'])->assertOk()
        ->assertJsonPath('data.sub.default_point_scoring_type', 'achievement')
        ->assertJsonPath('data.points.0.scoring_type', 'achievement')
        ->assertJsonPath('data.points.1.scoring_type', 'achievement');

    expect(GuidingPoint::query()->where('sub_criterion_id', $sub->id)->pluck('scoring_type')->unique()->all())->toBe([ScoringType::Achievement])
        ->and(Audit::query()->where('event', AuditEvent::CriteriaPointUpdated->value)->where('user_id', $actor->id)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::CriteriaSubUpdated->value)->where('user_id', $actor->id)->count())->toBe(1);
});

it('refuses a directly evaluated sub-criterion, a frozen version and an unknown type', function (): void {
    $draft = balancedDraft();
    signIn(criteriaOfficer());

    $this->putJson(pointTypePath(subOf($draft, 'M1.2')), ['scoring_type' => 'relative'])->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.sub_evaluated_directly');
    $this->putJson(pointTypePath(subOf($draft, 'M1.1')), ['scoring_type' => 'out_of_5'])->assertUnprocessable()
        ->assertJsonValidationErrors('scoring_type');
    $this->putJson(pointTypePath(subOf($draft, 'M1.1')), [])->assertUnprocessable()
        ->assertJsonValidationErrors('scoring_type');

    $published = publishVersion($draft);
    $this->putJson(pointTypePath(subOf($published, 'M1.1')), ['scoring_type' => 'relative'])->assertConflict()
        ->assertJsonPath('code', 'criteria.version_not_draft');
});

it('lets the System Administrator apply it and denies roles without criteria.manage', function (): void {
    $draft = balancedDraft();

    switchTo(systemAdmin())->putJson(pointTypePath(subOf($draft, 'M1.1')), ['scoring_type' => 'relative'])->assertOk();
    switchTo(staffUser(RoleKey::RequestFollower))->putJson(pointTypePath(subOf($draft, 'M1.1')), ['scoring_type' => 'score'])->assertForbidden();
});
