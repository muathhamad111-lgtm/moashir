<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Insights\MaturityBand;
use App\Models\Audit\Audit;
use App\Models\Insights\MaturityInterpretation;

const MATURITY_TEXTS = '/api/v1/content/maturity-texts';

/**
 * @return array{ar: string, en: string}
 */
function maturityText(string $suffix = ''): array
{
    return ['ar' => 'الجهة في مرحلة متقدمة من النضج المؤسسي'.$suffix, 'en' => 'The entity has reached an advanced institutional maturity'.$suffix];
}

it('lists the four generic band rows first, empty until written', function (): void {
    signIn(staffUser(RoleKey::ContentOfficer));

    $this->getJson(MATURITY_TEXTS)->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.band', 'emerging')
        ->assertJsonPath('data.3.band', 'leading')
        ->assertJsonPath('data.0.is_generic', true)
        ->assertJsonPath('data.0.text', null)
        ->assertJsonPath('meta.pagination.total', 4);
});

it('lets the Content Officer write a generic text and a criterion override, each audited', function (): void {
    insightsCriteria();
    $officer = staffUser(RoleKey::ContentOfficer);
    signIn($officer);

    $this->putJson(MATURITY_TEXTS.'/leading', ['text' => maturityText()])->assertOk()
        ->assertJsonPath('data.is_generic', true)
        ->assertJsonPath('data.text.en', maturityText()['en']);
    $this->putJson(MATURITY_TEXTS.'/leading', ['main_criterion_code' => 'M2', 'text' => maturityText(' M2')])->assertOk()
        ->assertJsonPath('data.main_criterion_code', 'M2')
        ->assertJsonPath('data.in_published_version', true);
    $this->putJson(MATURITY_TEXTS.'/leading', ['main_criterion_code' => 'M2', 'text' => maturityText(' M2')])->assertOk();

    $audits = Audit::query()->where('event', AuditEvent::MaturityTextsUpdated->value)->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->old_values)->toBe(['text' => null])
        ->and($audits[1]->new_values)->toMatchArray(['band' => 'leading', 'main_criterion_code' => 'M2'])
        ->and($audits[1]->user_id)->toBe($officer->id)
        ->and(MaturityInterpretation::query()->count())->toBe(5);
});

it('flags an override whose criterion is not in the published version and keeps it', function (): void {
    $old = insightsCriteria(['M9' => ['M9.1' => '100.00']]);
    archiveVersion($old);
    insightsCriteria();
    signIn(systemAdmin());

    $this->putJson(MATURITY_TEXTS.'/mature', ['main_criterion_code' => 'M9', 'text' => maturityText()])->assertOk()
        ->assertJsonPath('data.in_published_version', false);
    $this->getJson(MATURITY_TEXTS)->assertOk()->assertJsonPath('data.4.main_criterion_code', 'M9');
});

it('rejects markup, texts over 600 characters, missing languages and unknown criteria', function (array $payload, string $field): void {
    signIn(staffUser(RoleKey::ContentOfficer));

    $this->putJson(MATURITY_TEXTS.'/emerging', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'markup' => [['text' => ['ar' => '<b>نص</b>', 'en' => 'Text']], 'text.ar'],
    'too long' => [['text' => ['ar' => str_repeat('ن', 601), 'en' => 'Text']], 'text.ar'],
    'missing english' => [['text' => ['ar' => 'نص']], 'text.en'],
    'unknown criterion' => [['main_criterion_code' => 'Z9', 'text' => ['ar' => 'نص', 'en' => 'Text']], 'main_criterion_code'],
]);

it('answers 404 for an unknown band', function (): void {
    signIn(staffUser(RoleKey::ContentOfficer));

    $this->putJson(MATURITY_TEXTS.'/expert', ['text' => maturityText()])->assertNotFound();
});

it('deletes an override with an audit and refuses to delete a generic band row', function (): void {
    signIn(staffUser(RoleKey::ContentOfficer));
    $override = MaturityInterpretation::query()->create(['band' => MaturityBand::Mature, 'main_criterion_code' => 'M1', 'text' => maturityText()]);
    $generic = MaturityInterpretation::query()->whereNull('main_criterion_code')->where('band', MaturityBand::Mature)->sole();

    $this->deleteJson(MATURITY_TEXTS.'/'.$generic->id)->assertUnprocessable()->assertJsonPath('code', 'insights.generic_text_not_deletable');
    $this->deleteJson(MATURITY_TEXTS.'/'.$override->id)->assertNoContent();

    expect(MaturityInterpretation::query()->whereKey($override->id)->exists())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::MaturityTextsUpdated->value)->sole()->new_values)->toMatchArray(['deleted' => true, 'text' => null]);
});

it('keeps the maturity texts to the content managers', function (): void {
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->getJson(MATURITY_TEXTS)->assertForbidden();
    switchTo($owner)->putJson(MATURITY_TEXTS.'/leading', ['text' => maturityText()])->assertForbidden();
    switchTo(requestFollower())->getJson(MATURITY_TEXTS)->assertForbidden();
    switchTo(systemAdmin())->getJson(MATURITY_TEXTS)->assertOk();
});
