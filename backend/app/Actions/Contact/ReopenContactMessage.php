<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactMessageStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactThread;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ReopenContactMessage
{
    private const string VERB = 'reopen';

    public function __construct(private StaffActionReason $reasons, private ContactThread $thread, private AuditRecorder $audit) {}

    public function handle(User $actor, ContactMessage $message, ?string $reason): ContactMessage
    {
        $this->reasons->record($actor, $reason);

        return DB::transaction(function () use ($message): ContactMessage {
            $locked = $this->thread->lock($message);

            if ($locked->isOpen()) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $locked->forceFill([
                'status' => ContactMessageStatus::InProgress,
                'closed_at' => null,
                'closed_by_user_id' => null,
                'last_activity_at' => Date::now(),
            ])->save();

            $this->audit->record(new AuditEntry(AuditEvent::ContactMessageReopened, $locked, ['status' => ContactMessageStatus::Closed->value], [
                'status' => ContactMessageStatus::InProgress->value,
                'reference' => $locked->reference,
            ]));

            return $locked;
        });
    }
}
