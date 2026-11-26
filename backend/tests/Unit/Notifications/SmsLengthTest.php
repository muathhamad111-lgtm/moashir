<?php

declare(strict_types=1);

use App\Support\Sms\SmsLength;

it('counts the messages a text costs, GSM-7 or UCS-2', function (string $text, int $segments): void {
    expect(SmsLength::segments($text))->toBe($segments);
})->with([
    'empty' => ['', 0],
    'short English' => ['Your code is 1234', 1],
    'full English message' => [str_repeat('a', 160), 1],
    'English split' => [str_repeat('a', 161), 2],
    'extension characters count twice' => [str_repeat('€', 81), 2],
    'full Arabic message' => [str_repeat('ن', 70), 1],
    'Arabic split' => [str_repeat('ن', 71), 2],
    'Arabic at three parts' => [str_repeat('ن', 201), 3],
    'one Arabic letter in English' => [str_repeat('a', 69).'ن', 1],
]);
