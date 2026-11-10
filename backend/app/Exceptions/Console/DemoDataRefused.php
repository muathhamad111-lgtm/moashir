<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use RuntimeException;

final class DemoDataRefused extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function flagNotSet(): self
    {
        return new self('flag_not_set', 'Demo data is refused: APP_DEMO_DATA is not set to true for this environment.');
    }

    public static function postureNotDemo(): self
    {
        return new self('posture_not_demo', 'Demo data is refused: this environment is not in the demo posture (MOASHIR_DEMO is not true).');
    }

    public static function realDataPresent(int $foreignEntities): self
    {
        return new self('real_data_present', sprintf('Demo data is refused: %d entity record(s) were not created by the demo command.', $foreignEntities));
    }

    public static function alreadyInstalled(): self
    {
        return new self('already_installed', 'Demo data is already installed. Use moashir:demo:rotate-passwords to issue new demo passwords.');
    }

    public static function notInstalled(): self
    {
        return new self('not_installed', 'No demo accounts exist. Install the demo data first with moashir:demo:install.');
    }

    public static function insightsAlreadyInstalled(): self
    {
        return new self('insights_already_installed', 'The excellence-tools demo cases are already installed; nothing was changed.');
    }

    public static function insightsOnlyCombined(): self
    {
        return new self('options_combined', 'Demo data is refused: --insights-only cannot be combined with --content-only or --accounts-file.');
    }

    public static function openPaymentOrder(): self
    {
        return new self('open_payment_order', 'Demo data is refused: a demo entity has an open accredited payment order; run the command again after it is paid, cancelled or expired.');
    }

    public static function purgeOutsideDemoPosture(): self
    {
        return new self('purge_posture_not_demo', 'The demo purge is refused: this environment is not in the demo posture (MOASHIR_DEMO is not true).');
    }

    public static function purgeNeedsTableOwner(): self
    {
        return new self('purge_not_table_owner', 'The demo purge is refused: the database role does not own the guarded tables. Run it with the owner role used for migrations.');
    }

    public static function missingPrerequisite(string $what): self
    {
        return new self('missing_prerequisite', sprintf('Demo data is refused: %s. Run the reference-data commands of the runbook first.', $what));
    }

    public static function accountsFileRejected(string $why): self
    {
        return new self('accounts_file_rejected', sprintf('Demo data is refused: the accounts file %s.', $why));
    }
}
