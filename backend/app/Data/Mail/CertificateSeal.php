<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Certificates\CertificateLevel;

/**
 * The level seal a certificate e-mail shows: the official seal image of the level and its name.
 */
final readonly class CertificateSeal
{
    public function __construct(public CertificateLevel $level) {}
}
