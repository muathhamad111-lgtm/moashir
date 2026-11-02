<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Integrations\IntegrationCredential;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

const AI_SETTINGS = '/api/v1/settings/ai';

function fakeOpenAiModels(string $host = 'api.openai.com'): void
{
    fakeProviderRoutes([
        'GET '.$host.'/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'gpt-4.1-mini', 'object' => 'model', 'created' => 1744316542, 'owned_by' => 'system'],
            ['id' => 'gpt-4.1', 'object' => 'model', 'created' => 1744316542, 'owned_by' => 'system'],
            ['id' => 'text-embedding-3-small', 'object' => 'model', 'created' => 1705948997, 'owned_by' => 'system'],
            ['id' => 'gpt-4o-realtime-preview', 'object' => 'model', 'created' => 1727659998, 'owned_by' => 'system'],
            ['id' => 'whisper-1', 'object' => 'model', 'created' => 1677532384, 'owned_by' => 'openai-internal'],
        ]]),
        'POST '.$host.'/v1/responses' => Http::response([
            'id' => 'resp_fake', 'object' => 'response', 'status' => 'completed',
            'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'OK']]]],
            'usage' => ['input_tokens' => 21, 'output_tokens' => 2, 'total_tokens' => 23],
        ], 200, ['x-request-id' => 'req_fake_0001']),
    ]);
}

function fakeAnthropicModels(): void
{
    fakeProviderRoutes([
        'GET api.anthropic.com/v1/models' => Http::response(['data' => [
            ['type' => 'model', 'id' => 'claude-sonnet-4-6', 'display_name' => 'Claude Sonnet 4.6', 'max_tokens' => 64000],
            ['type' => 'model', 'id' => 'claude-2.1', 'display_name' => 'Claude 2.1', 'retires_at' => '2025-07-21T00:00:00Z'],
        ], 'has_more' => false]),
        'POST api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg_fake', 'type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'OK']],
            'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 18, 'output_tokens' => 2],
        ], 200, ['request-id' => 'req_fake_0002']),
    ]);
}

it('saves the OpenAI settings with a write-only key and shows the defaults', function (): void {
    signIn(systemAdmin());

    $response = $this->putJson(AI_SETTINGS, ['providers' => ['openai' => [
        'api_key' => FAKE_OPENAI_KEY,
        'model' => 'gpt-4.1-mini',
        'region' => 'eu',
        'project_id' => 'proj_fakeMoashir',
    ]], 'placement' => 'public_only'])
        ->assertOk()
        ->assertJsonPath('data.active_provider', null)
        ->assertJsonPath('data.mode', 'test')
        ->assertJsonPath('data.providers.openai.configured', true)
        ->assertJsonPath('data.providers.openai.api_key.set', true)
        ->assertJsonPath('data.providers.openai.api_key.last4', '0001')
        ->assertJsonPath('data.providers.openai.region', 'eu')
        ->assertJsonPath('data.providers.openai.project_id', 'proj_fakeMoashir')
        ->assertJsonPath('data.providers.openai.max_output_tokens', 1024)
        ->assertJsonPath('data.providers.openai.data_transfer_acknowledgement.acknowledged', false)
        ->assertJsonPath('data.providers.anthropic.configured', false)
        ->assertJsonPath('data.providers.anthropic.inference_geo', 'global');

    assertNoSecretIn((string) $response->getContent());
    expect($response->getContent())->not->toContain('google')->not->toContain('embedding');
});

it('activates a provider only after the cross-border acknowledgement and raises AI-04', function (): void {
    Notification::fake();
    $otherAdmin = systemAdmin();
    configureOpenAi();
    $admin = systemAdmin();
    signIn($admin);

    $this->putJson(AI_SETTINGS, ['active_provider' => 'openai'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ai.data_transfer_acknowledgement_required');

    $this->putJson(AI_SETTINGS, ['active_provider' => 'openai', 'providers' => ['openai' => ['data_transfer_acknowledged' => true]]])
        ->assertOk()
        ->assertJsonPath('data.active_provider', 'openai')
        ->assertJsonPath('data.providers.openai.data_transfer_acknowledgement.acknowledged', true)
        ->assertJsonPath('data.providers.openai.data_transfer_acknowledgement.acknowledged_by', ['id' => $admin->id, 'name' => $admin->name]);

    $this->putJson(AI_SETTINGS, ['providers' => ['openai' => ['data_transfer_acknowledged' => false]]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ai.data_transfer_acknowledgement_required');

    Notification::assertSentTo($otherAdmin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::AiProviderUpdated);
    Notification::assertNotSentTo($admin, EntityNotification::class);
});

it('validates the AI settings', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $this->putJson(AI_SETTINGS, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'Gemini is not offered' => [['active_provider' => 'google'], 'active_provider'],
    'Gemini fields are refused' => [['providers' => ['google' => ['api_key' => FAKE_OPENAI_KEY]]], 'providers'],
    'unknown OpenAI region' => [['providers' => ['openai' => ['region' => 'sa']]], 'providers.openai.region'],
    'short API key' => [['providers' => ['openai' => ['api_key' => 'sk-short']]], 'providers.openai.api_key'],
    'workspace without its prefix' => [['providers' => ['anthropic' => ['workspace_id' => 'workspace-1']]], 'providers.anthropic.workspace_id'],
    'inference outside the documented values' => [['providers' => ['anthropic' => ['inference_geo' => 'eu']]], 'providers.anthropic.inference_geo'],
    'too many answer tokens' => [['providers' => ['openai' => ['max_output_tokens' => 9000]]], 'providers.openai.max_output_tokens'],
    'embedding settings are not offered' => [['providers' => ['openai' => ['embedding_model' => 'text-embedding-3-small']]], 'providers.openai'],
    'unknown placement' => [['placement' => 'everywhere'], 'placement'],
]);

it('loads the chat models of the selected region with the stored key, before a model is chosen', function (): void {
    fakeOpenAiModels('eu.api.openai.com');
    storeCredential(IntegrationProvider::OpenAi, null, ['region' => 'eu', 'organization_id' => 'org_fakeMoashir'], ['api_key' => FAKE_OPENAI_KEY]);
    signIn(systemAdmin());

    $this->getJson(AI_SETTINGS.'/models?provider=openai')
        ->assertOk()
        ->assertJsonPath('meta.provider', 'openai')
        ->assertJsonPath('data.*.id', ['gpt-4.1', 'gpt-4.1-mini']);

    Http::assertSent(static fn (ClientRequest $request): bool => str_starts_with($request->url(), 'https://eu.api.openai.com/')
        && $request->hasHeader('Authorization', 'Bearer '.FAKE_OPENAI_KEY) && $request->hasHeader('OpenAI-Organization', 'org_fakeMoashir'));
});

it('lists the Anthropic models without retired ones and sends the pinned API version', function (): void {
    fakeAnthropicModels();
    configureAnthropic();
    signIn(systemAdmin());

    $this->getJson(AI_SETTINGS.'/models?provider=anthropic')
        ->assertOk()
        ->assertJsonPath('data', [['id' => 'claude-sonnet-4-6', 'display_name' => 'Claude Sonnet 4.6', 'max_output_tokens' => 64000]]);

    Http::assertSent(static fn (ClientRequest $request): bool => $request->hasHeader('x-api-key', FAKE_ANTHROPIC_KEY) && $request->hasHeader('anthropic-version', '2023-06-01'));
});

it('needs the provider to load models', function (): void {
    signIn(systemAdmin());

    $this->getJson(AI_SETTINGS.'/models')->assertUnprocessable()->assertJsonValidationErrors('provider');
    $this->getJson(AI_SETTINGS.'/models?provider=openai')->assertConflict()->assertJsonPath('code', 'integration.not_configured');
});

it('tests the AI connection through the model list', function (): void {
    fakeOpenAiModels();
    configureOpenAi();
    signIn(systemAdmin());

    $this->postJson(AI_SETTINGS.'/test-connection', ['provider' => 'openai'])
        ->assertOk()
        ->assertJsonPath('data.checks', [['key' => 'api_key_valid', 'ok' => true, 'detail_code' => null], ['key' => 'model_available', 'ok' => true, 'detail_code' => null]])
        ->assertJsonPath('data.info.model', 'gpt-4.1-mini');

    expect(Audit::query()->where('event', AuditEvent::AiConnectionTested->value)->count())->toBe(1);
});

it('fails the AI test when the model is no longer listed and raises AI-05', function (): void {
    Notification::fake();
    $otherAdmin = systemAdmin();
    fakeOpenAiModels();
    configureOpenAi(['model' => 'gpt-3.5-legacy']);
    signIn(systemAdmin());

    $this->postJson(AI_SETTINGS.'/test-connection', ['provider' => 'openai'])
        ->assertStatus(502)
        ->assertJsonPath('errors.provider_error_kind', ['misconfigured'])
        ->assertJsonPath('meta.result.checks.1.detail_code', 'model_not_listed');

    Notification::assertSentTo($otherAdmin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::AiProviderConnectionFailed);
});

it('runs one short test answer without provider-side storage or user identifiers', function (): void {
    fakeOpenAiModels();
    configureOpenAi([], ['is_active' => true, 'provider_refs' => ['data_transfer_acknowledged_at' => now()->toIso8601String()]]);
    signIn(systemAdmin());

    $this->postJson(AI_SETTINGS.'/test-answer')
        ->assertOk()
        ->assertJsonPath('data.provider', 'openai')
        ->assertJsonPath('data.model', 'gpt-4.1-mini')
        ->assertJsonPath('data.finish', 'complete')
        ->assertJsonPath('data.text', 'OK')
        ->assertJsonPath('data.provider_request_id', 'req_fake_0001');

    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/v1/responses')
        && $request['store'] === false
        && $request['max_output_tokens'] === 16
        && ! isset($request['user'])
        && ! isset($request['metadata']));
});

it('sends the US inference geography to Anthropic only when chosen', function (): void {
    fakeAnthropicModels();
    configureAnthropic(['inference_geo' => 'us']);
    signIn(systemAdmin());

    $this->postJson(AI_SETTINGS.'/test-answer', ['provider' => 'anthropic'])->assertOk()->assertJsonPath('data.finish', 'complete');

    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/v1/messages')
        && $request['inference_geo'] === 'us' && $request['max_tokens'] === 16 && ! isset($request['metadata']));
});

it('maps an exhausted OpenAI quota without retrying it', function (): void {
    Sleep::fake();
    fakeProviderRoutes(['POST api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'You exceeded your current quota.', 'type' => 'insufficient_quota', 'code' => 'insufficient_quota']], 429)]);
    configureOpenAi();
    signIn(systemAdmin());

    $this->postJson(AI_SETTINGS.'/test-answer', ['provider' => 'openai'])->assertStatus(502)->assertJsonPath('errors.provider_error_kind', ['quota_exhausted']);

    Http::assertSentCount(1);
});

it('retries an overloaded Anthropic answer once and then reports it unavailable', function (): void {
    Sleep::fake();
    fakeProviderRoutes(['POST api.anthropic.com/v1/messages' => Http::response(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529)]);
    configureAnthropic();
    signIn(systemAdmin());

    $this->postJson(AI_SETTINGS.'/test-answer', ['provider' => 'anthropic'])->assertStatus(502)->assertJsonPath('errors.provider_error_kind', ['unavailable']);

    Http::assertSentCount(2);
});

it('keeps the AI provider settings away from every other role, including the Content Officer', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(AI_SETTINGS)->assertForbidden();
    $this->putJson(AI_SETTINGS, ['placement' => 'public_only'])->assertForbidden();
    $this->getJson(AI_SETTINGS.'/models?provider=openai')->assertForbidden();
    $this->postJson(AI_SETTINGS.'/test-connection')->assertForbidden();
    $this->postJson(AI_SETTINGS.'/test-answer')->assertForbidden();
})->with(nonSystemAdminRoles());

it('stores AI rows per provider with one active at most', function (): void {
    configureOpenAi([], ['is_active' => true, 'provider_refs' => ['data_transfer_acknowledged_at' => now()->toIso8601String()]]);
    configureAnthropic([], ['provider_refs' => ['data_transfer_acknowledged_at' => now()->toIso8601String()]]);
    signIn(systemAdmin());

    $this->putJson(AI_SETTINGS, ['active_provider' => 'anthropic'])->assertOk()->assertJsonPath('data.active_provider', 'anthropic');

    expect(IntegrationCredential::query()->where('kind', 'ai')->where('is_active', true)->pluck('provider')->map->value->all())->toBe(['anthropic']);
});
