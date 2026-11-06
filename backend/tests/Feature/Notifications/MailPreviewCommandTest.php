<?php

declare(strict_types=1);

use App\Services\Notifications\MailSamples;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Mail\Transport\ArrayTransport;

function mailPreviewDirectory(): string
{
    $directory = storage_path('framework/testing/mail-preview-'.getmypid());
    $files = new Filesystem;
    $files->deleteDirectory($directory);
    $files->ensureDirectoryExists($directory);

    return $directory;
}

afterEach(function (): void {
    new Filesystem()->deleteDirectory(storage_path('framework/testing/mail-preview-'.getmypid()));
});

it('renders the main sample e-mails in Arabic and English without sending anything', function (): void {
    $directory = mailPreviewDirectory();
    config(['mail.default' => 'log']);

    $this->artisan('moashir:mail:preview', ['--out' => $directory])
        ->expectsOutputToContain('منصة مؤشر — رمز التحقق')
        ->expectsOutputToContain('Moashir — Reset your password')
        ->assertSuccessful();

    foreach (MailSamples::MAIN as $name) {
        foreach (['ar', 'en'] as $locale) {
            $html = (string) file_get_contents($directory.'/'.$name.'.'.$locale.'.html');

            expect($html)->toContain('<html lang="'.$locale.'"')
                ->and($html)->toContain('src="data:image/png;base64,')
                ->and($html)->not->toContain('cid:')
                ->and($directory.'/'.$name.'.'.$locale.'.txt')->toBeFile();
        }
    }

    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();

    expect(config('mail.default'))->toBe('log')
        ->and($transport)->toBeInstanceOf(ArrayTransport::class)
        ->and($transport instanceof ArrayTransport ? $transport->messages()->count() : 0)->toBe(count(MailSamples::MAIN) * 2);
});

it('renders every sample of one notification code in the requested language only', function (): void {
    $directory = mailPreviewDirectory();

    $this->artisan('moashir:mail:preview', ['code' => 'SEC-01', '--locale' => ['en'], '--out' => $directory])->assertSuccessful();

    expect(glob($directory.'/*.html'))->toHaveCount(2)
        ->and($directory.'/SEC-01.account.en.html')->toBeFile()
        ->and($directory.'/SEC-01.mailbox.en.txt')->toBeFile()
        ->and(glob($directory.'/*.ar.*'))->toBe([]);
});

it('refuses an unknown code, an unknown language or a missing output directory', function (array $arguments, string $error): void {
    $directory = mailPreviewDirectory();
    $arguments = array_map(static fn (mixed $value): mixed => $value === '{dir}' ? $directory : $value, $arguments);

    $this->artisan('moashir:mail:preview', $arguments)->expectsOutputToContain($error)->assertFailed();

    expect(glob($directory.'/*'))->toBe([]);
})->with([
    'unknown code' => [['code' => 'XYZ-01', '--out' => '{dir}'], 'Unknown notification code.'],
    'unknown language' => [['--locale' => ['fr'], '--out' => '{dir}'], 'The --locale option accepts ar and en only.'],
    'missing directory' => [['--out' => '/nonexistent/mail-preview'], 'Give an existing, writable --out directory.'],
    'no directory' => [[], 'Give an existing, writable --out directory.'],
]);
