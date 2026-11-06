<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Data\Contact\ContactAction;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactEntryKind;
use App\Enums\Contact\ContactMessageStatus;
use App\Events\Contact\ContactReplySent;
use App\Exceptions\Contact\ContactRejected;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactThread;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ReplyToContactMessage
{
    public function __construct(private StaffActionReason $reasons, private ContactThread $thread, private AuditRecorder $audit) {}

    public function handle(User $actor, ContactMessage $message, ContactAction $reply): ContactMessageEntry
    {
        $this->reasons->record($actor, $reply->reason);

        return DB::transaction(function () use ($actor, $message, $reply): ContactMessageEntry {
            $locked = $this->thread->lock($message);

            if (! $locked->isOpen()) {
                throw ContactRejected::closed();
            }

            $before = $locked->status->value;
            $entry = $this->thread->append($locked, ContactEntryKind::Reply, $reply->body, $actor);
            $now = Date::now();
            $locked->forceFill(['status' => ContactMessageStatus::Replied, 'replied_at' => $now, 'last_activity_at' => $now])->save();

            $this->audit->record(new AuditEntry(AuditEvent::ContactMessageReplied, $locked, ['status' => $before], [
                'status' => ContactMessageStatus::Replied->value,
                'reference' => $locked->reference,
                'entry_id' => $entry->id,
                'is_linked' => $locked->isLinked(),
            ]));

            ContactReplySent::dispatch($locked, $entry);

            return $entry;
        });
    }
}
