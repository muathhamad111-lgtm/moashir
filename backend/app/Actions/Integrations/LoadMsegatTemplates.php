<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Contracts\Integrations\SmsTemplateDirectory;
use App\Data\Integrations\ProviderSmsTemplate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\IntegrationRows;
use App\Services\Integrations\MsegatTemplateGuard;
use App\Services\Integrations\ProviderAdapterRegistry;
use App\Services\Integrations\ProviderRefsWriter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class LoadMsegatTemplates
{
    public function __construct(
        private IntegrationProbe $probe,
        private ProviderAdapterRegistry $adapters,
        private IntegrationRows $rows,
        private ProviderRefsWriter $refs,
    ) {}

    /**
     * @return list<ProviderSmsTemplate>
     */
    public function handle(): array
    {
        $credentials = $this->probe->configuredKey(IntegrationProvider::Msegat, 'api_key');

        try {
            $templates = $this->adapters->get(SmsTemplateDirectory::class, IntegrationProvider::Msegat)->templates($credentials);
        } catch (ProviderCallFailed $failure) {
            throw IntegrationRejected::providerError($failure->kind);
        }

        DB::transaction(function () use ($templates): void {
            $this->rows->lockKind(IntegrationKind::Sms);
            $row = $this->rows->find(IntegrationProvider::Msegat);

            if ($row instanceof IntegrationCredential) {
                $this->refs->put($row, [
                    MsegatTemplateGuard::APPROVED_TEMPLATES => array_map(static fn (ProviderSmsTemplate $template): array => $template->toArray(), $templates),
                    MsegatTemplateGuard::TEMPLATES_LOADED_AT => Date::now()->toIso8601String(),
                ], AuditEvent::SmsTemplatesLoaded);
            }
        });

        return $templates;
    }
}
