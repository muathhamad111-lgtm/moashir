<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Identity\GrantSuperAdmin;
use App\Actions\Identity\ListSuperAdmins;
use App\Actions\Identity\RevokeSuperAdmin;
use App\Enums\Identity\SuperAdminRevocation;
use App\Exceptions\Console\SuperAdminCommandRejected;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:super-admin')]
final class ManageSuperAdmin extends Command
{
    protected $signature = 'moashir:super-admin
        {action : grant, revoke or list}
        {--email= : Account email (grant defaults to SUPER_ADMIN_INITIAL_EMAIL)}
        {--name= : Full name, required only when creating the initial account}
        {--allow-last : Allow revoking the last Super Admin account}';

    protected $description = 'Grant, revoke or list Super Admin accounts (console only).';

    public function handle(GrantSuperAdmin $grant, RevokeSuperAdmin $revoke, ListSuperAdmins $list): int
    {
        try {
            return match ($this->argument('action')) {
                'grant' => $this->grant($grant),
                'revoke' => $this->revoke($revoke),
                'list' => $this->list($list),
                default => $this->rejectAction(),
            };
        } catch (SuperAdminCommandRejected|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function grant(GrantSuperAdmin $grant): int
    {
        $email = $this->targetEmail(useInitialEmailFallback: true);
        $this->confirmPhrase($email);

        $grant->handle($email, $this->stringOption('name'), $this->operator());
        $this->components->info(sprintf('Super Admin granted to %s. Two-factor enrolment is required at the next sign-in.', $email->masked()));

        return self::SUCCESS;
    }

    private function revoke(RevokeSuperAdmin $revoke): int
    {
        $email = $this->targetEmail(useInitialEmailFallback: false);
        $this->confirmPhrase($email);

        $revocation = $this->option('allow-last') === true ? SuperAdminRevocation::AllowLast : SuperAdminRevocation::KeepAtLeastOne;
        $revoke->handle($email, $revocation, $this->operator());
        $this->components->info(sprintf('Super Admin revoked from %s. All of its sessions were ended.', $email->masked()));

        return self::SUCCESS;
    }

    private function list(ListSuperAdmins $list): int
    {
        $this->table(['Email', 'MFA enrolled', 'Last sign-in'], array_map(
            static fn (array $row): array => [$row['email'], $row['mfa_enrolled'] ? 'yes' : 'no', $row['last_login_at'] ?? '-'],
            $list->handle(),
        ));

        return self::SUCCESS;
    }

    private function rejectAction(): int
    {
        $this->components->error('The action must be grant, revoke or list.');

        return self::INVALID;
    }

    private function targetEmail(bool $useInitialEmailFallback): EmailAddress
    {
        $email = $this->stringOption('email');

        if ($email === null && $useInitialEmailFallback) {
            $initial = config('moashir.super_admin.initial_email');
            $email = is_string($initial) && $initial !== '' ? $initial : null;
        }

        return $email === null ? throw SuperAdminCommandRejected::missingEmail() : new EmailAddress($email);
    }

    private function confirmPhrase(EmailAddress $email): void
    {
        $answer = $this->ask(sprintf('Type the full email of the account (%s) to confirm', $email->masked()));

        if (! is_string($answer) || mb_strtolower(trim($answer)) !== $email->value) {
            throw SuperAdminCommandRejected::notConfirmed();
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function operator(): string
    {
        $account = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid()) : false;

        return is_array($account) ? (string) $account['name'] : 'unknown';
    }
}
