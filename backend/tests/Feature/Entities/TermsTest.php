<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\TermsKind;
use App\Models\Audit\Audit;
use App\Models\Identity\TermsVersion;

it('serves the current terms and privacy policy to guests', function (): void {
    $terms = publishTerms();

    $this->getJson('/api/v1/terms/current')
        ->assertOk()
        ->assertJsonPath('data.terms.id', $terms->id)
        ->assertJsonPath('data.terms.version', '2026-11')
        ->assertJsonPath('data.terms.body.ar', 'نص الشروط والأحكام المعتمد لمنصة مؤشر.')
        ->assertJsonPath('data.privacy', null);
});

it('publishes a new current version from the console and audits it', function (): void {
    $previous = publishTerms();
    $publisher = systemAdmin();
    $arabic = temporaryListFile('الشروط والأحكام المحدّثة لمنصة مؤشر.');
    $english = temporaryListFile('The updated terms and conditions of the Moashir platform.');

    $this->artisan('moashir:terms:publish', [
        'kind' => 'terms',
        'label' => '2027-01',
        '--ar' => $arabic,
        '--en' => $english,
        '--effective-from' => '2027-01-01',
        '--publisher' => $publisher->email,
    ])->expectsOutputToContain('Published terms version 2027-01')->assertSuccessful();

    $current = TermsVersion::query()->current()->where('kind', TermsKind::Terms->value)->firstOrFail();

    expect($current->version)->toBe('2027-01')
        ->and($current->body->en)->toBe('The updated terms and conditions of the Moashir platform.')
        ->and($current->published_by_user_id)->toBe($publisher->id)
        ->and($previous->refresh()->is_current)->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::TermsPublished->value)->exists())->toBeTrue();
});

it('refuses to publish with invalid input', function (array $arguments): void {
    $publisher = systemAdmin();
    $file = temporaryListFile('نص معتمد من الجهة المالكة للمنصة.');

    $this->artisan('moashir:terms:publish', [
        'kind' => 'terms',
        'label' => '2027-02',
        '--ar' => $file,
        '--en' => $file,
        '--publisher' => $publisher->email,
        ...$arguments,
    ])->assertFailed();

    expect(TermsVersion::query()->count())->toBe(0);
})->with([
    'unknown kind' => [['kind' => 'cookies']],
    'missing file' => [['--en' => '/nonexistent/terms-en.txt']],
    'entity publisher' => [['--publisher' => 'nobody@moashir.example.sa']],
    'invalid date' => [['--effective-from' => '01/02/2027']],
    'invalid label' => [['label' => 'version one']],
]);
