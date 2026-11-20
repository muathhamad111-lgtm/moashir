<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Data\Mail\AccreditedProgress;
use App\Data\Mail\CertificateSeal;
use App\Data\Mail\MailBlocks;
use App\Data\Mail\RequestedItems;
use App\Data\Notifications\NoticeText;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final readonly class EntityNotice
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    public function __construct(
        public NotificationCode $code,
        public LocalizedText $title,
        public LocalizedText $body,
        public ?string $link,
        public bool $viaMail,
        public bool $viaInApp,
        public ?AssessmentPath $path = null,
        public ?NoticeText $text = null,
        public MailBlocks $blocks = new MailBlocks,
    ) {}

    public static function fromText(NotificationCode $code, NoticeText $text, ?string $link, ?AssessmentPath $path = null): self
    {
        return new self($code, $text->title(), $text->body(), $link, true, true, $path, $text);
    }

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function for(NotificationCode $code, string $audience, array $replacements, ?string $link): self
    {
        return self::fromText($code, new NoticeText('notifications.entities.'.$code->value.'.'.$audience, $replacements), $link);
    }

    /**
     * The same notice carrying the items the reviewer asked for, listed in its e-mail.
     */
    public function withItems(RequestedItems $items): self
    {
        return $this->withBlocks($this->blocks->withItems($items));
    }

    /**
     * The same notice showing in its e-mail where the accredited request stands.
     */
    public function withProgress(AccreditedProgress $progress): self
    {
        return $this->withBlocks($this->blocks->withProgress($progress));
    }

    /**
     * The same notice showing the certificate's level seal in its e-mail.
     */
    public function withSeal(CertificateSeal $seal): self
    {
        return $this->withBlocks($this->blocks->withSeal($seal));
    }

    public function withBlocks(MailBlocks $blocks): self
    {
        return new self($this->code, $this->title, $this->body, $this->link, $this->viaMail, $this->viaInApp, $this->path, $this->text, $blocks);
    }

    public function inAppOnly(): self
    {
        return new self($this->code, $this->title, $this->body, $this->link, false, true, $this->path, $this->text, $this->blocks);
    }

    public function mailOnly(): self
    {
        return new self($this->code, $this->title, $this->body, $this->link, true, false, $this->path, $this->text, $this->blocks);
    }

    public function forPath(AssessmentPath $path): self
    {
        return new self($this->code, $this->title, $this->body, $this->link, $this->viaMail, $this->viaInApp, $path, $this->text, $this->blocks);
    }
}
