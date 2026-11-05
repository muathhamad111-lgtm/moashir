<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Data\Contact\ContactIdentity;
use App\Enums\Identity\Locale;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;

final class AccountContactIdentity
{
    public function of(User $user, Locale $locale): ContactIdentity
    {
        return new ContactIdentity($user->name, new EmailAddress($user->email), $this->organizationOf($user, $locale));
    }

    private function organizationOf(User $user, Locale $locale): ?string
    {
        $entity = $user->entity_id === null ? null : Entity::query()->withTrashed()->find($user->entity_id);

        return $entity instanceof Entity ? $entity->name->in($locale->value) : null;
    }
}
