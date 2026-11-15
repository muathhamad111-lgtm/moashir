<?php

declare(strict_types=1);

use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\PhoneNumber;

it('normalizes and masks an e-mail address', function (): void {
    $email = new EmailAddress('  Sara.Alotaibi@Moashir.Example.SA ');

    expect($email->value)->toBe('sara.alotaibi@moashir.example.sa')
        ->and($email->masked())->toBe('s***@moashir.example.sa');
});

it('refuses an invalid e-mail address', function (): void {
    expect(fn (): EmailAddress => new EmailAddress('not-an-email'))->toThrow(InvalidArgumentException::class);
});

it('accepts Saudi mobile numbers in E.164 only', function (): void {
    expect(new PhoneNumber('+966512345678')->masked())->toBe('+9665*****678')
        ->and(fn (): PhoneNumber => new PhoneNumber('0512345678'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PhoneNumber => new PhoneNumber('+966112345678'))->toThrow(InvalidArgumentException::class);
});

it('requires both languages for localized text', function (): void {
    expect(fn (): LocalizedText => new LocalizedText('نص عربي', ''))->toThrow(InvalidArgumentException::class)
        ->and(LocalizedText::fromArray(['ar' => 'المقيّم', 'en' => 'Assessor'])->toArray())->toBe(['ar' => 'المقيّم', 'en' => 'Assessor'])
        ->and(LocalizedText::fromTranslation('roles.assessor.label')->en)->toBe('Assessor');
});

it('joins translated items into one localized list', function (): void {
    $list = LocalizedText::listOf(['entities.profile_fields.license_number', 'entities.profile_fields.founded_on']);

    expect($list->ar)->toBe('رقم الترخيص، تاريخ التأسيس')
        ->and($list->en)->toBe('Licence number, Founding date');
});
