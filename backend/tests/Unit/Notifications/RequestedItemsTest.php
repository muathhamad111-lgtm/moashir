<?php

declare(strict_types=1);

use App\Data\Mail\RequestedItem;
use App\Data\Mail\RequestedItems;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Support\Mail\SampleRequestedItems;

function requestedItem(string $code, ?string $note = null): RequestedItem
{
    return new RequestedItem($code, null, CompletionItemKind::Cv, $note);
}

it('shows at most ten items and counts the hidden ones', function (int $total, int $shown, int $hidden): void {
    $items = new RequestedItems(array_map(static fn (int $index): RequestedItem => requestedItem('M'.$index), range(1, $total)));

    expect($items->count())->toBe($total)
        ->and($items->shown())->toHaveCount($shown)
        ->and($items->hiddenCount())->toBe($hidden)
        ->and($items->isEmpty())->toBeFalse();
})->with([
    'one item' => [1, 1, 0],
    'exactly ten' => [10, 10, 0],
    'eleven' => [11, 10, 1],
    'the request maximum' => [100, 10, 90],
]);

it('is empty without items', function (): void {
    $items = new RequestedItems([]);

    expect($items->isEmpty())->toBeTrue()
        ->and($items->shown())->toBe([])
        ->and($items->hiddenCount())->toBe(0);
});

it('has a note only when the reviewer wrote something', function (?string $note, bool $hasNote): void {
    expect(requestedItem('M1.1', $note)->hasNote())->toBe($hasNote);
})->with([
    'no note' => [null, false],
    'blank note' => ['   ', false],
    'written note' => ['أرفق النسخة المعتمدة.', true],
]);

it('gives sample items only to the completion e-mails, with the note in the preview language', function (): void {
    $arabic = SampleRequestedItems::for(NotificationCode::AccreditedCompletionRequested, Locale::Arabic);
    $english = SampleRequestedItems::for(NotificationCode::AdvancedReturnedForCompletion, Locale::English);

    expect($arabic->count())->toBe(3)
        ->and($arabic->items[0]->note)->toStartWith('الملف المرفق')
        ->and($english->items[0]->note)->toStartWith('The attached file')
        ->and(SampleRequestedItems::for(NotificationCode::AccreditedSubmitted, Locale::Arabic)->isEmpty())->toBeTrue();
});
