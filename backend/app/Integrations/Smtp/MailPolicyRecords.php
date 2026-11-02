<?php

declare(strict_types=1);

namespace App\Integrations\Smtp;

use App\Contracts\Integrations\HostResolver;
use App\Services\Integrations\SmtpHostGuard;

final readonly class MailPolicyRecords
{
    public function __construct(private HostResolver $resolver) {}

    /**
     * @return array{spf_record_found: bool, dmarc_policy: string|null}
     */
    public function of(string $fromAddress): array
    {
        $domain = mb_strtolower(substr((string) strrchr($fromAddress, '@'), 1));

        if (! SmtpHostGuard::isHostname($domain)) {
            return ['spf_record_found' => false, 'dmarc_policy' => null];
        }

        $spf = array_any($this->resolver->textRecordsOf($domain), static fn (string $record): bool => str_starts_with(mb_strtolower($record), 'v=spf1'));

        return ['spf_record_found' => $spf, 'dmarc_policy' => $this->dmarcPolicy($domain)];
    }

    private function dmarcPolicy(string $domain): ?string
    {
        foreach ($this->resolver->textRecordsOf('_dmarc.'.$domain) as $record) {
            if (preg_match('/^v=DMARC1;.*\bp=(none|quarantine|reject)\b/i', $record, $matches) === 1) {
                return mb_strtolower($matches[1]);
            }
        }

        return null;
    }
}
