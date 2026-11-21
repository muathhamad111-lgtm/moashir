<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Data\Contact\ContactAction;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactEntryKind;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactThread;
use Illuminate\Support\Facades\DB;

final readonly class AddContactMessageNote
{
    public function __construct(private StaffActionReason $reasons, private ContactThread $thread, private AuditRecorder $audit) {}

    public function handle(User $actor, ContactMessage $message, ContactAction $note): ContactMessageEntry
    {
        $this->reasons->record($actor, $note->reason);

        return DB::transaction(function () use ($actor, $message, $note): ContactMessageEntry {
            $locked = $this->thread->lock($message);
            $entry = $this->thread->append($locked, ContactEntryKind::Note, $note->body, $actor);

            $this->audit->record(new AuditEntry(AuditEvent::ContactMessageNoteAdded, $locked, [], [
                'reference' => $locked->reference,
                'entry_id' => $entry->id,
            ]));

            return $entry;
        });
    }
}
