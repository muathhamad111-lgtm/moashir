<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\EmailAddress;
use InvalidArgumentException;

final readonly class MailSampleRun
{
    public const int MAX_RECIPIENTS = 3;

    /**
     * @param  list<EmailAddress>  $recipients
     * @param  list<Locale>  $locales
     */
    public function __construct(public array $recipients, public ?NotificationCode $code, public array $locales)
    {
        if ($recipients === [] || count($recipients) > self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException(sprintf('A sample run needs 1 to %d recipients.', self::MAX_RECIPIENTS));
        }

        if ($locales === []) {
            throw new InvalidArgumentException('A sample run needs at least one language.');
        }
    }

    /**
     * @return list<string>
     */
    public function recipientDomains(): array
    {
        return array_values(array_unique(array_map(static fn (EmailAddress $recipient): string => $recipient->domain(), $this->recipients)));
    }
}
