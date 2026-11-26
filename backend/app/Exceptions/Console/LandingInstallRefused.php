<?php

declare(strict_types=1);

namespace App\Exceptions\Console;

use RuntimeException;

final class LandingInstallRefused extends RuntimeException
{
    public static function editorNotEligible(): self
    {
        return new self('The landing content was not installed: the --editor e-mail does not belong to an active staff account that may manage the public content.');
    }

    public static function noEditor(): self
    {
        return new self('The landing content was not installed: no active Content Officer or System Administrator exists. Create one, or pass --editor=<e-mail> of a staff account that may manage the public content.');
    }
}
