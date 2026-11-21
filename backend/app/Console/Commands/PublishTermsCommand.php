<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Identity\TermsPublication;
use App\Enums\Identity\TermsKind;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Rules\FreeOfHtmlMarkup;
use App\Services\Identity\TermsVersionPublisher;
use App\Support\RichText\TitledText;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:terms:publish')]
final class PublishTermsCommand extends Command
{
    protected $signature = 'moashir:terms:publish
        {kind : terms or privacy}
        {label : Version label, for example 2026-11}
        {--ar= : Path to the Arabic text (a leading "# " line becomes the title); omit --ar and --en to publish the stored draft with this label}
        {--en= : Path to the English text (a leading "# " line becomes the title)}
        {--effective-from= : Effective date (Y-m-d), today by default}
        {--publisher= : E-mail of the active staff or Super Admin account that publishes the text}';

    protected $description = 'Publish a new current version of the terms of use or of the privacy policy: either the stored draft with the given label, or a new version from text files supplied by the client.';

    public function handle(TermsVersionPublisher $publisher): int
    {
        try {
            $kind = $this->kind();
            $label = $this->label();
            $account = $this->publisher();
            $version = $this->publishesStoredDraft()
                ? $publisher->publishDraft($account, $this->storedDraft($kind, $label), $this->effectiveFrom())
                : $publisher->publishNew($account, $this->publication($kind, $label));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $this->components->info(sprintf('Published %s version %s (id %d) as the current version.', $version->kind, $version->version, $version->id));

        return self::SUCCESS;
    }

    private function kind(): TermsKind
    {
        return TermsKind::tryFrom($this->stringArgument('kind')) ?? throw new InvalidArgumentException('The kind must be terms or privacy.');
    }

    private function label(): string
    {
        $label = $this->stringArgument('label');

        if (preg_match('/^[A-Za-z0-9._-]{1,20}$/', $label) !== 1) {
            throw new InvalidArgumentException('The version label may contain up to 20 letters, digits, dots, dashes or underscores.');
        }

        return $label;
    }

    private function publishesStoredDraft(): bool
    {
        return $this->stringOption('ar') === '' && $this->stringOption('en') === '';
    }

    private function storedDraft(TermsKind $kind, string $label): TermsVersion
    {
        $draft = TermsVersion::query()->ofKind($kind)->drafts()->where('version', $label)->first();

        return $draft ?? throw new InvalidArgumentException('No draft with this version label exists for this kind; give --ar and --en to publish new texts.');
    }

    private function publication(TermsKind $kind, string $label): TermsPublication
    {
        if (TermsVersion::query()->ofKind($kind)->where('version', $label)->exists()) {
            throw new InvalidArgumentException('This version label already exists for this kind.');
        }

        $arabic = TitledText::fromMarkdown($this->textFrom('ar'));
        $english = TitledText::fromMarkdown($this->textFrom('en'));
        $title = $arabic->title !== null && $english->title !== null ? new LocalizedText($arabic->title, $english->title) : null;

        return new TermsPublication($kind, $label, new LocalizedText($arabic->body, $english->body), $this->effectiveFrom(), $title);
    }

    private function effectiveFrom(): CarbonImmutable
    {
        $value = $this->stringOption('effective-from');

        if ($value === '') {
            return CarbonImmutable::today();
        }

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('Y-m-d', $value) : null;

        return $date instanceof CarbonImmutable ? $date->startOfDay() : throw new InvalidArgumentException('The effective date must use the Y-m-d format.');
    }

    private function publisher(): User
    {
        $email = new EmailAddress($this->stringOption('publisher'));
        $publisher = User::query()->withEmail($email)->whereNull('entity_id')->where('status', UserStatus::Active)->first();

        return $publisher ?? throw new InvalidArgumentException('The publisher must be an active staff or Super Admin account.');
    }

    private function textFrom(string $locale): string
    {
        $path = $this->stringOption($locale);
        $text = $path !== '' && is_file($path) ? trim((string) file_get_contents($path)) : '';

        if ($text === '' || TitledText::fromMarkdown($text)->body === '') {
            throw new InvalidArgumentException(sprintf('The --%s option must point to a readable, non-empty text file.', $locale));
        }

        if (FreeOfHtmlMarkup::contains($text)) {
            throw new InvalidArgumentException(sprintf('The --%s text must not contain HTML markup; use the simple format for headings, lists and tables.', $locale));
        }

        return $text;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? trim($value) : '';
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? trim($value) : '';
    }
}
