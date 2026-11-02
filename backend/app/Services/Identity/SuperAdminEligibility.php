<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\Locale;
use App\Enums\Identity\SuperAdminRevocation;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Console\SuperAdminCommandRejected;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Config\Repository;

final readonly class SuperAdminEligibility
{
    public function __construct(private Repository $config) {}

    public function candidateFor(EmailAddress $email, ?string $name): User
    {
        $user = User::query()->with('roles')->withEmail($email)->first();

        if ($user !== null) {
            $this->assertGrantable($user);

            return $user;
        }

        if (! $this->isBootstrap($email)) {
            throw SuperAdminCommandRejected::userNotFound();
        }

        return $this->bootstrapAccount($email, $name);
    }

    public function assertRevocable(User $user, SuperAdminRevocation $revocation): void
    {
        if (! $user->isSuperAdmin()) {
            throw SuperAdminCommandRejected::notSuperAdmin();
        }

        if ($revocation === SuperAdminRevocation::KeepAtLeastOne && User::query()->superAdmins()->count() <= 1) {
            throw SuperAdminCommandRejected::lastAccount();
        }
    }

    private function assertGrantable(User $user): void
    {
        match (true) {
            $user->isSuperAdmin() => throw SuperAdminCommandRejected::alreadySuperAdmin(),
            $user->status !== UserStatus::Active => throw SuperAdminCommandRejected::userInactive(),
            $user->entity_id !== null => throw SuperAdminCommandRejected::entityUser(),
            $user->roles->isNotEmpty() => throw SuperAdminCommandRejected::holdsOtherRoles(),
            default => $this->assertBelowLimit(),
        };
    }

    private function assertBelowLimit(): void
    {
        $maximum = $this->config->integer('moashir.super_admin.max_accounts');

        if (User::query()->superAdmins()->count() >= $maximum) {
            throw SuperAdminCommandRejected::limitReached($maximum);
        }
    }

    private function isBootstrap(EmailAddress $email): bool
    {
        $initial = $this->config->get('moashir.super_admin.initial_email');

        return is_string($initial)
            && $initial !== ''
            && (new EmailAddress($initial))->value === $email->value
            && User::query()->superAdmins()->doesntExist();
    }

    private function bootstrapAccount(EmailAddress $email, ?string $name): User
    {
        if ($name === null || trim($name) === '') {
            throw SuperAdminCommandRejected::missingName();
        }

        $user = new User(['name' => trim($name), 'email' => $email->value, 'locale' => Locale::Arabic]);
        $user->forceFill(['status' => UserStatus::Active])->save();

        return $user;
    }
}
