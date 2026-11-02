<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

interface SmtpTransportFactory
{
    public function build(ResolvedCredentials $credentials): EsmtpTransport;
}
