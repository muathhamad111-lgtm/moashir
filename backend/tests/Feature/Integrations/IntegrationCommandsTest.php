<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Models\Audit\Audit;
use App\Models\Integrations\IntegrationCredential;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

function bootstrapMoyasarEnvironment(): void
{
    config()->set('moashir.integrations.environment.payment', [
        'mode' => 'test',
        'publishable_key' => sprintf(FAKE_MOYASAR_PUBLISHABLE, 'test'),
        'secret_key' => sprintf(FAKE_MOYASAR_SECRET, 'test'),
        'webhook_secret' => FAKE_WEBHOOK_SECRET,
    ]);
}

it('imports the environment bootstrap of a kind into an encrypted, active settings row', function (): void {
    bootstrapMoyasarEnvironment();

    $this->artisan('moashir:integrations:import-env', ['kind' => 'payment'])
        ->expectsOutputToContain('Imported payment (moyasar, test)')
        ->assertSuccessful();

    $row = IntegrationCredential::query()->firstOrFail();
    expect($row->is_active)->toBeTrue()
        ->and($row->mode->value)->toBe('test')
        ->and($row->secrets['secret_key'] ?? null)->toBe(sprintf(FAKE_MOYASAR_SECRET, 'test'))
        ->and((string) DB::table('integration_credentials')->value('secrets'))->not->toContain('sk_test_');

    $audit = Audit::query()->where('event', AuditEvent::IntegrationEnvImported->value)->where('auditable_type', $row->getMorphClass())->firstOrFail();
    expect($audit->new_values['secret_fields'] ?? null)->toEqualCanonicalizing(['secret_key', 'webhook_secret'])
        ->and($audit->user_id)->toBeNull();
    assertNoSecretInAudits();
});

it('refuses to import over settings that already exist or without complete environment values', function (): void {
    $this->artisan('moashir:integrations:import-env', ['kind' => 'payment'])->expectsOutputToContain('no complete payment credentials')->assertFailed();

    bootstrapMoyasarEnvironment();
    configureMoyasar();

    $this->artisan('moashir:integrations:import-env', ['kind' => 'payment'])->expectsOutputToContain('already configured')->assertFailed();
    $this->artisan('moashir:integrations:import-env', ['kind' => 'fax'])->assertExitCode(2);

    expect(IntegrationCredential::query()->count())->toBe(1);
});

it('re-encrypts every stored secret with the current key after a rotation', function (): void {
    configureTaqnyat();
    configureOpenAi();
    $before = DB::table('integration_credentials')->orderBy('id')->pluck('secrets')->all();
    $oldKey = config('app.key');
    $newKey = 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
    config()->set('app.key', $newKey);
    config()->set('app.previous_keys', [$oldKey]);
    app()->forgetInstance('encrypter');
    IntegrationCredential::encryptUsing(app('encrypter'));

    $this->artisan('moashir:integrations:reencrypt')->expectsOutputToContain('Re-encrypted the secrets of 2 integration rows.')->assertSuccessful();

    $after = DB::table('integration_credentials')->orderBy('id')->pluck('secrets')->all();
    $current = new Encrypter(base64_decode(substr($newKey, 7), true), 'aes-256-cbc');

    expect($after)->not->toBe($before)
        ->and(json_decode($current->decryptString((string) $after[0]), true)['bearer_token'] ?? null)->toBe(FAKE_TAQNYAT_TOKEN)
        ->and(Audit::query()->where('event', AuditEvent::IntegrationSecretsReencrypted->value)->count())->toBe(2);

    IntegrationCredential::encryptUsing(null);
});
