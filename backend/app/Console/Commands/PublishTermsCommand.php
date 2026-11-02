<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Identity\PublishTermsVersion;
use App\Data\Identity\TermsPublication;
use App\Enums\Identity\TermsKind;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
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
        {--ar= : Path to the Arabic text}
        {--en= : Path to the English text}
        {--effective-from= : Effective date (Y-m-d), today by default}
        {--publisher= : E-mail of the active staff or Super Admin account that publishes the text}';

    protected $description = 'Publish a new current version of the terms and conditions or of the privacy policy from text files supplied by the client.';

    public function handle(PublishTermsVersion $publishTerms): int
    {
        try {
            $publication = $this->publication();
            $publisher = $this->publisher();
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        if (TermsVersion::query()->where('kind', $publication->kind->value)->where('version', $publication->version)->exists()) {
            $this->components->error('This version label is already published for this kind.');

            return self::FAILURE;
        }

        $version = $publishTerms->handle($publisher, $publication);
        $this->components->info(sprintf('Published %s version %s (id %d) as the current version.', $version->kind, $version->version, $version->id));

        return self::SUCCESS;
    }

    private function publication(): TermsPublication
    {
        $kind = TermsKind::tryFrom($this->stringArgument('kind')) ?? throw new InvalidArgumentException('The kind must be terms or privacy.');
        $label = $this->stringArgument('label');

        if (preg_match('/^[A-Za-z0-9._-]{1,20}$/', $label) !== 1) {
            throw new InvalidArgumentException('The version label may contain up to 20 letters, digits, dots, dashes or underscores.');
        }

        return new TermsPublication(
            $kind,
            $label,
            new LocalizedText($this->textFrom('ar'), $this->textFrom('en')),
            $this->effectiveFrom(),
        );
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

        if ($text === '') {
            throw new InvalidArgumentException(sprintf('The --%s option must point to a readable, non-empty text file.', $locale));
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
