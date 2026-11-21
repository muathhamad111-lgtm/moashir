<?php

declare(strict_types=1);

use App\Data\Mail\MailSampleRun;
use App\Enums\Identity\Locale;
use App\Support\Mail\SampleLocales;
use App\Support\ValueObjects\EmailAddress;

it('accepts one to three inboxes and lists their domains once', function (): void {
    $run = new MailSampleRun([
        new EmailAddress('one@moashir.example.sa'),
        new EmailAddress('two@moashir.example.sa'),
        new EmailAddress('three@inbox.example.com'),
    ], null, [Locale::Arabic]);

    expect($run->recipientDomains())->toBe(['moashir.example.sa', 'inbox.example.com']);
});

it('refuses a run without inboxes, with too many, or without a language', function (array $recipients, array $locales): void {
    expect(fn (): MailSampleRun => new MailSampleRun($recipients, null, $locales))->toThrow(InvalidArgumentException::class);
})->with([
    'no inbox' => [[], [Locale::Arabic]],
    'four inboxes' => [array_map(static fn (int $index): EmailAddress => new EmailAddress("qa{$index}@example.sa"), range(1, 4)), [Locale::Arabic]],
    'no language' => [[new EmailAddress('qa@example.sa')], []],
]);

it('reads the language option as both languages by default and refuses other values', function (mixed $option, ?array $expected): void {
    expect(SampleLocales::from($option))->toBe($expected);
})->with([
    'none' => [[], [Locale::Arabic, Locale::English]],
    'not a list' => [null, [Locale::Arabic, Locale::English]],
    'english only' => [['en'], [Locale::English]],
    'unknown' => [['ar', 'fr'], null],
]);

it('gives the domain of an address', function (): void {
    expect(new EmailAddress(' Sara@Moashir.Example.SA ')->domain())->toBe('moashir.example.sa');
});
