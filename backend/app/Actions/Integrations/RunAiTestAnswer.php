<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Contracts\Integrations\AiProvider;
use App\Data\Audit\AuditEntry;
use App\Data\Integrations\AiPrompt;
use App\Data\Integrations\AiTestAnswer;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Integrations\IntegrationNotifier;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\IntegrationRows;
use App\Services\Integrations\ProviderAdapterRegistry;

final readonly class RunAiTestAnswer
{
    private const int MAX_OUTPUT_TOKENS = 16;

    private const string INSTRUCTIONS = 'This is a connection test of the Moashir platform. Reply with the single word OK.';

    private const string QUESTION = 'Connection test.';

    public function __construct(
        private IntegrationProbe $probe,
        private IntegrationRows $rows,
        private ProviderAdapterRegistry $adapters,
        private AuditRecorder $audit,
        private IntegrationNotifier $notifier,
    ) {}

    public function handle(User $actor, ?IntegrationProvider $provider): AiTestAnswer
    {
        $provider ??= $this->rows->active(IntegrationKind::Ai)->provider ?? throw IntegrationRejected::notConfigured(IntegrationKind::Ai);
        $credentials = $this->probe->configured($provider);

        try {
            $answer = $this->adapters->get(AiProvider::class, $provider)->answer($credentials, new AiPrompt(self::INSTRUCTIONS, self::QUESTION, [], self::MAX_OUTPUT_TOKENS));
        } catch (ProviderCallFailed $failure) {
            $this->notifier->notify(NotificationCode::AiProviderConnectionFailed, $actor->id, ['provider' => $provider->value, 'error' => $failure->kind->value]);

            throw IntegrationRejected::providerError($failure->kind);
        }

        $this->audit->record(new AuditEntry(AuditEvent::AiTestAnswered, $actor, [], [
            'provider' => $provider->value,
            'model' => $credentials->string('model'),
            'finish' => $answer->finish->value,
            'input_tokens' => $answer->inputTokens,
            'output_tokens' => $answer->outputTokens,
        ]));

        return new AiTestAnswer($provider, $credentials->string('model'), $answer);
    }
}
