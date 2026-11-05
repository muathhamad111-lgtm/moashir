<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Mail\MailSample;
use App\Data\Mail\RenderedMail;
use App\Enums\Identity\Locale;
use App\Exceptions\Console\MailSampleNotRendered;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Traits\Localizable;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final readonly class MailPreviewRenderer
{
    use Localizable;

    private const string MAILER = 'array';

    public function __construct(private MailManager $mail, private MailChannel $channel) {}

    public function render(MailSample $sample, Locale $locale): RenderedMail
    {
        $email = $this->withLocale($locale->value, fn (): Email => $this->send($sample));
        $html = $this->body($email->getHtmlBody());
        $inline = array_values(array_filter($email->getAttachments(), static fn (DataPart $part): bool => $part->getDisposition() === 'inline'));

        return new RenderedMail(
            (string) $email->getSubject(),
            $html,
            $this->withInlineImages($html, $inline),
            $this->body($email->getTextBody()),
            count($inline),
        );
    }

    private function send(MailSample $sample): Email
    {
        $previous = $this->mail->getDefaultDriver();
        $this->mail->setDefaultDriver(self::MAILER);

        try {
            $sent = $this->channel->send($sample->notifiable, $sample->notification);
        } finally {
            $this->mail->setDefaultDriver($previous);
        }

        $email = $sent?->getSymfonySentMessage()->getOriginalMessage();

        return $email instanceof Email ? $email : throw MailSampleNotRendered::named($sample->name);
    }

    /**
     * @param  list<DataPart>  $images
     */
    private function withInlineImages(string $html, array $images): string
    {
        foreach ($images as $image) {
            $html = str_replace(
                'cid:'.$image->getContentId(),
                sprintf('data:%s/%s;base64,%s', $image->getMediaType(), $image->getMediaSubtype(), base64_encode($image->getBody())),
                $html,
            );
        }

        return $html;
    }

    /**
     * @param  resource|string|null  $body
     */
    private function body(mixed $body): string
    {
        return is_string($body) ? $body : '';
    }
}
