<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Mail\MailSample;
use App\Data\Mail\RenderedMail;
use App\Enums\Identity\Locale;
use Symfony\Component\Mime\Part\DataPart;

final readonly class MailPreviewRenderer
{
    public function __construct(private MailSampleComposer $composer) {}

    public function render(MailSample $sample, Locale $locale): RenderedMail
    {
        $email = $this->composer->compose($sample, $locale);
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
