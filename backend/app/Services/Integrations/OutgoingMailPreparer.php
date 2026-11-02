<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use App\Services\Settings\IntegrationSettings;
use App\Support\ScalarValue;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final readonly class OutgoingMailPreparer
{
    public function __construct(private IntegrationSettings $settings, private Application $app) {}

    public function prepare(RawMessage $message, ResolvedCredentials $credentials): RawMessage
    {
        if (! $message instanceof Email) {
            return $message;
        }

        $email = clone $message;
        $email->from(new Address($credentials->string('from_address'), $this->fromName($credentials)));

        if ($credentials->string('reply_to') !== '') {
            $email->replyTo($credentials->string('reply_to'));
        }

        if ($credentials->mode->isTest()) {
            $this->redirectToTestMailbox($email);
        }

        return $email;
    }

    private function fromName(ResolvedCredentials $credentials): string
    {
        $names = $credentials->publicConfig['from_name'] ?? null;
        $names = is_array($names) ? $names : [];
        $locale = $this->app->getLocale() === 'en' ? 'en' : 'ar';

        return ScalarValue::string($names[$locale] ?? $names['ar'] ?? null);
    }

    private function redirectToTestMailbox(Email $email): void
    {
        $testAddress = $this->settings->emailTestAddress();

        if ($testAddress === null) {
            throw new TransportException('The e-mail test mode needs a test mailbox.');
        }

        $email->getHeaders()->remove('Cc');
        $email->getHeaders()->remove('Bcc');
        $email->to($testAddress);
    }
}
