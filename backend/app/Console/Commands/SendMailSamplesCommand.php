<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\SendMailSamples;
use App\Data\Mail\MailSampleRun;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Console\MailSamplesRefused;
use App\Support\Mail\SampleLocales;
use App\Support\StringList;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:mail:send-samples')]
final class SendMailSamplesCommand extends Command
{
    protected $signature = 'moashir:mail:send-samples
        {code? : Notification code, e.g. ACC-09 (omit for the main e-mails)}
        {--to=* : Test inbox address, 1 to 3 (repeat the option)}
        {--locale=* : ar and/or en (default: both)}';

    protected $description = 'Send the sample e-mails, with fictional data, to test inboxes through the saved SMTP settings. Staging and local only.';

    public function handle(SendMailSamples $sendSamples): int
    {
        $error = $this->inputError();

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        try {
            $sent = $sendSamples->handle($this->sampleRun());
        } catch (MailSamplesRefused $refusal) {
            $this->components->error($refusal->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%d sample e-mail(s) with fictional data were sent to %d inbox(es).', $sent, count($this->recipients())));

        return self::SUCCESS;
    }

    private function inputError(): ?string
    {
        $addresses = $this->addresses();

        return match (true) {
            $addresses === [] => 'Give at least one --to address.',
            count($addresses) > MailSampleRun::MAX_RECIPIENTS => sprintf('Give at most %d --to addresses.', MailSampleRun::MAX_RECIPIENTS),
            ! $this->areValid($addresses) => 'Every --to value must be a valid e-mail address.',
            $this->argument('code') !== null && ! $this->code() instanceof NotificationCode => 'Unknown notification code.',
            SampleLocales::from($this->option('locale')) === null => 'The --locale option accepts ar and en only.',
            default => null,
        };
    }

    private function sampleRun(): MailSampleRun
    {
        return new MailSampleRun($this->recipients(), $this->code(), SampleLocales::from($this->option('locale')) ?? []);
    }

    /**
     * @return list<string>
     */
    private function addresses(): array
    {
        return array_values(array_unique(array_map(static fn (string $address): string => mb_strtolower(trim($address)), StringList::from($this->option('to')))));
    }

    /**
     * @param  list<string>  $addresses
     */
    private function areValid(array $addresses): bool
    {
        try {
            array_map(static fn (string $address): EmailAddress => new EmailAddress($address), $addresses);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @return list<EmailAddress>
     */
    private function recipients(): array
    {
        return array_map(static fn (string $address): EmailAddress => new EmailAddress($address), $this->addresses());
    }

    private function code(): ?NotificationCode
    {
        return NotificationCode::tryFrom((string) $this->argument('code'));
    }
}
