<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactEntryKind;
use App\Enums\Contact\ContactMessageStatus;
use App\Events\Contact\ContactFollowUpReceived;
use App\Exceptions\Contact\ContactRejected;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactThread;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AddSenderFollowUp
{
    public function __construct(private ContactThread $thread, private AuditRecorder $audit) {}

    public function handle(User $sender, ContactMessage $message, string $body): ContactMessageEntry
    {
        return DB::transaction(function () use ($sender, $message, $body): ContactMessageEntry {
            $locked = $this->thread->lock($message);

            if (! $locked->isOpen()) {
                throw ContactRejected::closed();
            }

            $before = $locked->status;
            $entry = $this->thread->append($locked, ContactEntryKind::Message, $body, $sender);
            $now = Date::now();
            $locked->forceFill([
                'status' => $before === ContactMessageStatus::New ? ContactMessageStatus::New : ContactMessageStatus::InProgress,
                'last_activity_at' => $now,
                'sender_seen_at' => $now,
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::ContactMessageFollowUpReceived, $locked, ['status' => $before->value], [
                'status' => $locked->status->value,
                'reference' => $locked->reference,
                'entry_id' => $entry->id,
            ]));

            ContactFollowUpReceived::dispatch($locked);

            return $entry;
        });
    }
}
