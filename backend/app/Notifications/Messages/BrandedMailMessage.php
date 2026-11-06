<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

final class BrandedMailMessage extends MailMessage
{
    public ?string $title = null;

    public ?string $code = null;

    /**
     * @var list<string>
     */
    public array $quotedLines = [];

    public function __construct(public readonly MailReason $reason) {}

    public static function make(object $notifiable, MailReason $reason): self
    {
        $message = new self($reason);

        return $notifiable instanceof User
            ? $message->greeting(__('mail.greeting.named', ['name' => $notifiable->name]))
            : $message;
    }

    /**
     * @param  string  $subject
     */
    public function subject($subject): static
    {
        $this->title = $subject;

        return parent::subject(__('mail.subject', ['subject' => $subject]));
    }

    public function code(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function quote(string $paragraph): static
    {
        $this->quotedLines[] = trim($paragraph);

        return $this;
    }

    /**
     * @param  mixed  $line
     */
    public function with($line): static
    {
        $isText = is_string($line) || $line instanceof Htmlable;

        if (! $isText || ! $this->hasHighlight()) {
            return parent::with($line);
        }

        $this->outroLines[] = $this->formatLine($line);

        return $this;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        return [
            ...parent::data(),
            'title' => $this->title,
            'code' => $this->code,
            'quotedLines' => $this->quotedLines,
            'reason' => $this->reason,
        ];
    }

    private function hasHighlight(): bool
    {
        return $this->code !== null || $this->quotedLines !== [];
    }
}
