<?php

declare(strict_types=1);

use App\Data\Entities\SoftDeletionInstruction;
use App\Support\ValueObjects\EntityName;
use App\Support\ValueObjects\GovernanceGrade;

it('requires an Arabic entity name and makes the English one optional', function (): void {
    $name = new EntityName('  جمعية الأمل الخيرية ', ' ');

    expect($name->ar)->toBe('جمعية الأمل الخيرية')
        ->and($name->en)->toBeNull()
        ->and($name->in('en'))->toBe('جمعية الأمل الخيرية')
        ->and($name->toLocalizedText()->en)->toBe('جمعية الأمل الخيرية')
        ->and(fn (): EntityName => new EntityName(' '))->toThrow(InvalidArgumentException::class);
});

it('resolves the English entity name when present', function (): void {
    $name = EntityName::fromArray(['ar' => 'وقف الخير التنموي', 'en' => 'Al-Khair Development Waqf']);

    expect($name->in('en'))->toBe('Al-Khair Development Waqf')
        ->and($name->in('ar'))->toBe('وقف الخير التنموي')
        ->and($name->equals(new EntityName('وقف الخير التنموي', 'Al-Khair Development Waqf')))->toBeTrue();
});

it('keeps governance grades as two-decimal percentages between 0 and 100', function (string|int $input, string $expected): void {
    expect(GovernanceGrade::of($input)->toDecimal())->toBe($expected);
})->with([
    ['87.5', '87.50'],
    ['0', '0.00'],
    [100, '100.00'],
    ['99.99', '99.99'],
]);

it('rejects governance grades outside the percentage range or with more decimals', function (string $input): void {
    expect(fn (): GovernanceGrade => GovernanceGrade::of($input))->toThrow(InvalidArgumentException::class);
})->with(['100.01', '-0.01', '87.455', 'advanced']);

it('accepts a deletion only when the acknowledged requests match the open ones exactly', function (array $open, bool $confirm, array $acknowledged, bool $expected): void {
    expect(new SoftDeletionInstruction('سبب موثق للحذف.', $confirm, $acknowledged)->acknowledges($open))->toBe($expected);
})->with([
    'no open requests' => [[], false, [], true],
    'same set in another order' => [['ACC-1', 'ADV-2'], true, ['ADV-2', 'ACC-1'], true],
    'missing confirmation flag' => [['ACC-1'], false, ['ACC-1'], false],
    'missing one request' => [['ACC-1', 'ADV-2'], true, ['ACC-1'], false],
    'extra request' => [['ACC-1'], true, ['ACC-1', 'BAG-3'], false],
    'duplicates are ignored' => [['ACC-1'], true, ['ACC-1', 'ACC-1'], true],
]);
