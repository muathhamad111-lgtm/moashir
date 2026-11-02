<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum SmtpEncryption: string
{
    case StartTls = 'starttls';
    case Tls = 'tls';

    public function usesImplicitTls(): bool
    {
        return $this === self::Tls;
    }
}
