<?php

declare(strict_types=1);

namespace App\Services\Cms\Landing;

use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Console\LandingInstallRefused;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class LandingEditorResolver
{
    private const array DEFAULT_ROLES = [RoleKey::ContentOfficer, RoleKey::SystemAdmin];

    public function resolve(?string $email): User
    {
        return $email === null ? $this->defaultEditor() : $this->namedEditor($email);
    }

    private function namedEditor(string $email): User
    {
        try {
            $address = new EmailAddress($email);
        } catch (InvalidArgumentException) {
            throw LandingInstallRefused::editorNotEligible();
        }

        $editor = $this->activeStaff()->withEmail($address)->first();

        if (! $editor instanceof User || ! $this->mayManageContent($editor)) {
            throw LandingInstallRefused::editorNotEligible();
        }

        return $editor;
    }

    private function defaultEditor(): User
    {
        foreach (self::DEFAULT_ROLES as $role) {
            $editor = $this->activeStaff()->role($role->value)->orderBy('id')->get()->first($this->mayManageContent(...));

            if ($editor instanceof User) {
                return $editor;
            }
        }

        throw LandingInstallRefused::noEditor();
    }

    /**
     * @return Builder<User>
     */
    private function activeStaff(): Builder
    {
        return User::query()->staff()->withoutSuperAdmins()->where('status', UserStatus::Active);
    }

    private function mayManageContent(User $user): bool
    {
        return ! $user->isLocked() && $user->can('manage', CmsPage::class);
    }
}
