<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactMessageStatus;
use App\Events\Contact\ContactMessageAssigned;
use App\Exceptions\Contact\ContactRejected;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactHandlers;
use App\Services\Contact\ContactThread;
use Illuminate\Support\Facades\DB;

final readonly class AssignContactMessage
{
    public function __construct(
        private StaffActionReason $reasons,
        private ContactHandlers $handlers,
        private ContactThread $thread,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, ContactMessage $message, ?int $assigneeId, ?string $reason): ContactMessage
    {
        $this->reasons->record($actor, $reason);

        return DB::transaction(function () use ($actor, $message, $assigneeId): ContactMessage {
            $locked = $this->thread->lock($message);

            if (! $locked->isOpen()) {
                throw ContactRejected::closed();
            }

            $assignee = $assigneeId === null ? null : ($this->handlers->find($assigneeId) ?? throw ContactRejected::assigneeNotEligible());

            if ($locked->assigned_to_user_id === $assignee?->id) {
                return $locked;
            }

            $this->apply($locked, $assignee);

            if ($assignee instanceof User) {
                ContactMessageAssigned::dispatch($locked, $assignee->id, $actor->id);
            }

            return $locked;
        });
    }

    private function apply(ContactMessage $locked, ?User $assignee): void
    {
        $before = ['assigned_to_user_id' => $locked->assigned_to_user_id, 'status' => $locked->status->value];

        $locked->assigned_to_user_id = $assignee?->id;

        if ($assignee instanceof User && $locked->status === ContactMessageStatus::New) {
            $locked->status = ContactMessageStatus::InProgress;
        }

        $locked->save();

        $this->audit->record(new AuditEntry(AuditEvent::ContactMessageAssigned, $locked, $before, [
            'assigned_to_user_id' => $locked->assigned_to_user_id,
            'status' => $locked->status->value,
        ]));
    }
}
