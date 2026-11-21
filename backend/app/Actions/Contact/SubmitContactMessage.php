<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Data\Contact\ContactIdentity;
use App\Data\Contact\ContactReceipt;
use App\Data\Contact\ContactSubmission;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactEntryKind;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Identity\TermsKind;
use App\Events\Contact\ContactMessageReceived;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\AccountContactIdentity;
use App\Services\Contact\ContactReferences;
use App\Services\Contact\ContactThread;
use App\Services\Identity\CurrentTermsVersions;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

final readonly class SubmitContactMessage
{
    public function __construct(
        private ContactReferences $references,
        private AccountContactIdentity $accounts,
        private CurrentTermsVersions $terms,
        private ContactThread $thread,
        private AuditRecorder $audit,
    ) {}

    public function handle(ContactSubmission $submission, ?User $sender): ContactReceipt
    {
        $receivedAt = Date::now();

        if ($submission->isAutomated) {
            Log::info('contact.submission_dropped', ['reason' => 'honeypot']);

            return new ContactReceipt($this->references->decoy($receivedAt), $receivedAt);
        }

        $message = DB::transaction(fn (): ContactMessage => $this->store($submission, $sender, $receivedAt));

        return new ContactReceipt($message->reference, $message->created_at, $message->isLinked() ? $message->public_id : null);
    }

    private function store(ContactSubmission $submission, ?User $sender, CarbonInterface $receivedAt): ContactMessage
    {
        $identity = $this->identityOf($submission, $sender);
        $message = ContactMessage::query()->create([
            'reference' => $this->references->next($receivedAt),
            'status' => ContactMessageStatus::New,
            'enquiry_type' => $submission->enquiryType,
            'sender_name' => $identity->name,
            'sender_email' => $identity->email->value,
            'sender_mobile' => $submission->mobile?->value,
            'organization_name' => $identity->organization,
            'locale' => $submission->locale,
            'user_id' => $sender?->id,
            'entity_id' => $sender?->entity_id,
            'privacy_terms_version_id' => $this->terms->of(TermsKind::Privacy)?->id,
            'privacy_consent_at' => $receivedAt,
            'last_activity_at' => $receivedAt,
        ]);

        $this->thread->append($message, ContactEntryKind::Message, $submission->message, $sender);
        $this->audit->record(new AuditEntry(AuditEvent::ContactMessageReceived, $message, [], [
            'reference' => $message->reference,
            'enquiry_type' => $message->enquiry_type->value,
            'is_linked' => $message->isLinked(),
        ]));

        ContactMessageReceived::dispatch($message);

        return $message;
    }

    private function identityOf(ContactSubmission $submission, ?User $sender): ContactIdentity
    {
        if ($sender instanceof User) {
            return $this->accounts->of($sender, $submission->locale);
        }

        return $submission->guestIdentity ?? throw new LogicException('A guest submission carries the sender identity.');
    }
}
