<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Data\Contact\ContactAddressRequest;
use App\Enums\Audit\AuditEvent;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactMessageExporter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;

final readonly class ExportContactMessagesOfAddress
{
    public function __construct(
        private ContactMessageExporter $exporter,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array{generated_at: string, email: string, messages: list<array<string, mixed>>}
     */
    public function handle(User $actor, ContactAddressRequest $request): array
    {
        $this->context->recordReason($request->reason);
        $messages = $this->exporter->rows(ContactMessage::query()->where('sender_email', $request->email->value));

        $this->audit->record(new AuditEntry(AuditEvent::ContactMessagesExported, $actor, [], [
            'email' => $request->email->masked(),
            'messages' => count($messages),
            'format' => 'json',
        ], ['privacy', 'export']));

        return ['generated_at' => Date::now()->toIso8601String(), 'email' => $request->email->value, 'messages' => $messages];
    }
}
