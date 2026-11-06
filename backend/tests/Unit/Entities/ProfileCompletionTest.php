<?php

declare(strict_types=1);

use App\Enums\Entities\ProfileCompletionItem;
use App\Support\ValueObjects\ProfileCompletion;

/**
 * @return array<string, bool>
 */
function profileChecks(bool $required, bool $optional = false): array
{
    $checks = [];

    foreach (ProfileCompletionItem::cases() as $item) {
        $checks[$item->value] = $item->isRequired() ? $required : $optional;
    }

    return $checks;
}

it('floors the share of the complete required items', function (array $checks, int $percent, bool $isComplete): void {
    $completion = new ProfileCompletion($checks);

    expect($completion->percent())->toBe($percent)
        ->and($completion->isComplete())->toBe($isComplete);
})->with([
    'nothing' => [profileChecks(false), 0, false],
    'only the optional phone, website and logo' => [profileChecks(false, true), 0, false],
    'one of eight' => [[...profileChecks(false), 'basic_data' => true], 12, false],
    'six of eight without the licence number and the founding date' => [[...profileChecks(true), 'license_number' => false, 'founded_on' => false], 75, false],
    'seven of eight without the founding date' => [[...profileChecks(true), 'founded_on' => false], 87, false],
    'seven of eight without the unified national number' => [[...profileChecks(true), 'unified_number' => false], 87, false],
    'every required item' => [profileChecks(true), 100, true],
    'every item' => [profileChecks(true, true), 100, true],
]);

it('lists every item in order with its required flag', function (): void {
    $items = new ProfileCompletion(profileChecks(true))->toArray()['items'];

    expect(array_column($items, 'key'))->toBe([
        'basic_data', 'account_owner', 'governance_grade', 'employees_count', 'annual_beneficiaries_count', 'unified_number', 'license_number',
        'founded_on', 'phone', 'website_url', 'logo',
    ])->and(array_column($items, 'is_required'))->toBe([true, true, true, true, true, true, true, true, false, false, false]);
});

it('never counts the optional phone, website or logo', function (): void {
    $withoutOptional = new ProfileCompletion(profileChecks(true));
    $withOptional = new ProfileCompletion(profileChecks(true, true));

    expect($withoutOptional->percent())->toBe($withOptional->percent())
        ->and(array_map(static fn (ProfileCompletionItem $item): string => $item->value, array_values(array_filter(ProfileCompletionItem::cases(), static fn (ProfileCompletionItem $item): bool => ! $item->isRequired()))))
        ->toBe(['phone', 'website_url', 'logo']);
});

it('refuses a completion without every check', function (): void {
    new ProfileCompletion(['basic_data' => true]);
})->throws(InvalidArgumentException::class);
