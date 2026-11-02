<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

dataset('roles without criteria access', [
    'assessor' => [RoleKey::Assessor],
    'requests follower' => [RoleKey::RequestFollower],
    'content officer' => [RoleKey::ContentOfficer],
    'finance officer' => [RoleKey::FinanceOfficer],
    'award secretary' => [RoleKey::AwardSecretary],
]);

it('lists criteria versions newest first with pagination and a status filter', function (): void {
    $published = publishVersion(draftVersion(['version_number' => 1]));
    draftVersion(['version_number' => 2]);
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/versions')
        ->assertOk()
        ->assertJsonPath('data.0.version_number', 2)
        ->assertJsonPath('data.0.status', 'draft')
        ->assertJsonPath('data.1.status', 'published')
        ->assertJsonPath('meta.pagination.total', 2);

    $this->getJson('/api/v1/criteria/versions?filter[status]=published')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.scale_kind', 'of_10');
});

it('lets the system administrator read versions', function (): void {
    draftVersion();
    signIn(systemAdmin());

    $this->getJson('/api/v1/criteria/versions')->assertOk()->assertJsonCount(1, 'data');
});

it('validates the versions list query', function (string $query, string $field): void {
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/versions?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown status' => ['filter[status]=deleted', 'filter.status'],
    'unknown filter' => ['filter[label]=x', 'filter'],
    'page size above the limit' => ['per_page=500', 'per_page'],
]);

it('denies the criteria versions to roles without criteria access', function (RoleKey $role): void {
    $version = draftVersion();
    signIn(staffUser($role));

    $this->getJson('/api/v1/criteria/versions')->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->getJson('/api/v1/criteria/versions/'.$version->id)->assertForbidden();
    $this->postJson('/api/v1/criteria/versions', [])->assertForbidden();
})->with('roles without criteria access');

it('denies criteria versions to entity users and guests', function (): void {
    $this->getJson('/api/v1/criteria/versions')->assertUnauthorized();

    signIn(entityUser());
    $this->getJson('/api/v1/criteria/versions')->assertForbidden();
});

it('clones the published version into a new draft with the same codes', function (): void {
    $published = installedSeedCriteria();
    $actor = criteriaOfficer();
    signIn($actor);

    $response = $this->postJson('/api/v1/criteria/versions', ['label' => ['ar' => 'الإصدار الثاني', 'en' => 'Second version']])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version_number', 2)
        ->assertJsonPath('data.cloned_from_version_id', $published->id)
        ->assertJsonPath('data.consistent', true)
        ->assertJsonPath('data.label.en', 'Second version');

    $draft = CriteriaVersion::query()->findOrFail($response->json('data.id'));
    $sourceCodes = MainCriterion::query()->where('criteria_version_id', $published->id)->orderBy('sort_order')->pluck('code')->all();
    $draftMains = MainCriterion::query()->where('criteria_version_id', $draft->id)->orderBy('sort_order')->get();

    expect($draftMains->pluck('code')->all())->toBe($sourceCodes)
        ->and($draftMains->first()?->cloned_from_id)->toBe(mainOf($published, 'M1')->id)
        ->and(subOf($draft, 'M1.1')->guidingPoints()->count())->toBe(3)
        ->and(subOf($draft, 'M1.1')->attachmentRequirements()->count())->toBe(2)
        ->and(Audit::query()->where('event', AuditEvent::CriteriaVersionCreated->value)->where('user_id', $actor->id)->exists())->toBeTrue();
});

it('creates an empty draft when nothing has been published yet', function (): void {
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions', [])
        ->assertCreated()
        ->assertJsonPath('data.version_number', 1)
        ->assertJsonPath('data.cloned_from_version_id', null)
        ->assertJsonPath('data.consistent', false);
});

it('allows only one open draft at a time', function (): void {
    draftVersion();
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions', [])->assertConflict()->assertJsonPath('code', 'criteria.draft_exists');
});

it('clones a chosen archived version', function (): void {
    $archived = archiveVersion(publishVersion(balancedDraft()));
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions', ['clone_from_version_id' => $archived->id])
        ->assertCreated()
        ->assertJsonPath('data.cloned_from_version_id', $archived->id)
        ->assertJsonPath('data.consistent', true);
});

it('refuses to clone a draft or an unknown version', function (): void {
    $draft = CriteriaVersion::factory()->create();
    $draft->delete();
    signIn(criteriaOfficer());

    foreach ([$draft->id, 999999] as $source) {
        $this->postJson('/api/v1/criteria/versions', ['clone_from_version_id' => $source])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'criteria.clone_source_invalid')
            ->assertJsonValidationErrors('clone_from_version_id');
    }
});

it('validates the new draft payload', function (array $payload, string $field): void {
    signIn(criteriaOfficer());

    $this->postJson('/api/v1/criteria/versions', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'label without English' => [['label' => ['ar' => 'الإصدار الثاني']], 'label.en'],
    'label with an unknown language' => [['label' => ['ar' => 'الإصدار الثاني', 'en' => 'Second', 'fr' => 'Deuxième']], 'label'],
    'non numeric source' => [['clone_from_version_id' => 'latest'], 'clone_from_version_id'],
]);

it('keeps draft creation away from the system administrator', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/criteria/versions', [])->assertForbidden();
});

it('discards a draft, hides its tree and audits the discard', function (): void {
    $draft = balancedDraft();
    $main = mainOf($draft, 'M1');
    signIn(criteriaOfficer());

    $this->deleteJson('/api/v1/criteria/versions/'.$draft->id)->assertNoContent();

    expect(CriteriaVersion::withTrashed()->findOrFail($draft->id)->trashed())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::CriteriaVersionDiscarded->value)->exists())->toBeTrue();

    $this->getJson('/api/v1/criteria/versions/'.$draft->id)->assertNotFound();
    $this->getJson('/api/v1/criteria/mains/'.$main->id)->assertNotFound();
    $this->postJson('/api/v1/criteria/versions', [])->assertCreated();
});

it('refuses to discard a published version', function (): void {
    $published = publishVersion(balancedDraft());
    signIn(criteriaOfficer());

    $this->deleteJson('/api/v1/criteria/versions/'.$published->id)
        ->assertConflict()
        ->assertJsonPath('code', 'criteria.version_not_draft')
        ->assertJsonPath('errors.version_status', ['published']);
});

it('shows a version and its consistency report', function (): void {
    $draft = draftVersion();
    $main = mainIn($draft, 'M1', '1000.00');
    subIn($main, 'M1.1', '600.00');
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/versions/'.$draft->id)->assertOk()->assertJsonPath('data.id', $draft->id);

    $this->getJson('/api/v1/criteria/versions/'.$draft->id.'/check')
        ->assertOk()
        ->assertJsonPath('data.consistent', false)
        ->assertJsonPath('data.problems.0.node_type', 'main')
        ->assertJsonPath('data.problems.0.node_id', $main->id)
        ->assertJsonPath('data.problems.0.problem', 'weights_mismatch')
        ->assertJsonPath('data.problems.0.expected_weight', '1000.00')
        ->assertJsonPath('data.problems.0.actual_weight', '600.00')
        ->assertJsonPath('data.problems.1.problem', 'no_active_point')
        ->assertJsonPath('data.problems.1.code', 'M1.1');
});

it('translates the consistency messages', function (): void {
    $draft = draftVersion();
    mainIn($draft, 'M1', '1000.00');
    signIn(criteriaOfficer());

    $arabic = $this->getJson('/api/v1/criteria/versions/'.$draft->id.'/check')->json('data.problems.0.message');
    $english = $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/criteria/versions/'.$draft->id.'/check')->json('data.problems.0.message');

    expect($arabic)->toContain('M1')->toContain('1000.00')
        ->and($english)->toBe('The active sub-criteria of M1 total 0.00, but they must equal the main criterion weight of 1000.00.');
});
