<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Data\Contact\ContactAddressRequest;
use App\Enums\Audit\AuditEvent;
use App\Enums\Platform\HousekeepingTask;
use App\Models\Contact\ContactMessage;
use App\Models\Platform\HousekeepingRun;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactMessageDeletion;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class EraseContactMessagesOfAddress
{
    public function __construct(
        private ContactMessageDeletion $deletion,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array{erased_messages: int, erased_entries: int}
     */
    public function handle(ContactAddressRequest $request): array
    {
        $this->context->recordReason($request->reason);
        $startedAt = Date::now();

        return DB::transaction(function () use ($request, $startedAt): array {
            $ids = ContactMessage::query()->where('sender_email', $request->email->value)->lockForUpdate()->pluck('id')->all();
            $deleted = $this->deletion->delete(array_values(array_map(ScalarValue::int(...), $ids)));
            $counts = ['erased_messages' => $deleted['messages'], 'erased_entries' => $deleted['entries']];

            $run = HousekeepingRun::query()->create([
                'task' => HousekeepingTask::ContactMessageErasure,
                'counts' => $counts,
                'started_at' => $startedAt,
                'completed_at' => Date::now(),
            ]);

            $this->audit->record(new AuditEntry(AuditEvent::ContactMessagesErased, $run, [], [
                ...$counts,
                'email' => $request->email->masked(),
            ], ['privacy', 'erasure']));

            return $counts;
        });
    }
}
