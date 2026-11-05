<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Mail\RenderedMail;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Services\Notifications\MailPreviewRenderer;
use App\Services\Notifications\MailSamples;
use App\Support\StringList;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:mail:preview')]
final class PreviewMailCommand extends Command
{
    protected $signature = 'moashir:mail:preview
        {code? : Notification code, e.g. REG-02 (omit for the main e-mails)}
        {--locale=* : ar and/or en (default: both)}
        {--out= : Existing, writable directory for the HTML and plain-text files}';

    protected $description = 'Render sample e-mails with fictional data to HTML and plain-text files. Sends nothing.';

    public function handle(MailSamples $samples, MailPreviewRenderer $renderer, Filesystem $files): int
    {
        $output = rtrim((string) $this->option('out'), DIRECTORY_SEPARATOR);
        $code = NotificationCode::tryFrom((string) $this->argument('code'));
        $locales = $this->locales();
        $error = $this->inputError($output !== '' && $files->isDirectory($output) && $files->isWritable($output), $code, $locales);

        if ($error !== null || $locales === null) {
            $this->components->error($error ?? 'Invalid input.');

            return self::FAILURE;
        }

        foreach ($locales as $locale) {
            foreach ($code instanceof NotificationCode ? $samples->forCode($code, $locale) : $samples->main($locale) as $sample) {
                $name = $sample->name.'.'.$locale->value;
                $mail = $renderer->render($sample, $locale);
                $this->write($files, $output.DIRECTORY_SEPARATOR.$name, $mail);
                $this->components->twoColumnDetail($name, $mail->subject);
            }
        }

        $this->components->info('Sample e-mails rendered with fictional data. Nothing was sent.');

        return self::SUCCESS;
    }

    /**
     * @param  list<Locale>|null  $locales
     */
    private function inputError(bool $isOutputWritable, ?NotificationCode $code, ?array $locales): ?string
    {
        return match (true) {
            ! $isOutputWritable => 'Give an existing, writable --out directory.',
            $this->argument('code') !== null && ! $code instanceof NotificationCode => 'Unknown notification code.',
            $locales === null => 'The --locale option accepts ar and en only.',
            default => null,
        };
    }

    /**
     * @return list<Locale>|null
     */
    private function locales(): ?array
    {
        $requested = StringList::from($this->option('locale'));
        $locales = [];

        foreach ($requested === [] ? ['ar', 'en'] : $requested as $value) {
            $locale = Locale::tryFrom($value);

            if (! $locale instanceof Locale) {
                return null;
            }

            $locales[] = $locale;
        }

        return $locales;
    }

    private function write(Filesystem $files, string $base, RenderedMail $mail): void
    {
        $files->put($base.'.html', $mail->browserHtml);
        $files->put($base.'.txt', $mail->subject."\n\n".$mail->text);
    }
}
