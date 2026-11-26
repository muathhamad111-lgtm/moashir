<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;

final readonly class MailerPosture
{
    public const string SENDING_MAILER = 'settings';

    private const array NON_SENDING_TRANSPORTS = ['log', 'array'];

    private const int MAX_NESTING = 3;

    public function __construct(private Repository $config, private DemoPosture $posture, private LoggerInterface $logger) {}

    public function enforce(): void
    {
        $mailer = $this->config->get('mail.default');

        if (! $this->posture->isRealProduction() || ! is_string($mailer) || ! $this->keepsMailOnTheServer($mailer, 0)) {
            return;
        }

        $this->config->set('mail.default', self::SENDING_MAILER);
        $this->logger->critical('platform.mailer_refused', ['mailer' => $mailer, 'replacement' => self::SENDING_MAILER]);
    }

    private function keepsMailOnTheServer(string $mailer, int $depth): bool
    {
        $definition = $this->config->get('mail.mailers.'.$mailer);

        if (! is_array($definition)) {
            return false;
        }

        if (in_array($definition['transport'] ?? null, self::NON_SENDING_TRANSPORTS, true)) {
            return true;
        }

        $members = is_array($definition['mailers'] ?? null) ? $definition['mailers'] : [];

        return $depth < self::MAX_NESTING
            && array_any($members, fn (mixed $member): bool => is_string($member) && $this->keepsMailOnTheServer($member, $depth + 1));
    }
}
