<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Enums\Contact\ContactEntryKind;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FindOwnContactMessage
{
    public function handle(User $sender, string $publicId): ContactMessage
    {
        return ContactMessage::query()
            ->sentBy($sender->id)
            ->withSenderPreview()
            ->where('public_id', $publicId)
            ->with(['entries' => static fn (HasMany $entries): HasMany => $entries->whereIn('kind', ContactEntryKind::senderVisible())])
            ->firstOrFail();
    }
}
