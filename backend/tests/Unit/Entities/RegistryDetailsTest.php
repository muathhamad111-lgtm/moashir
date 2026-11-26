<?php

declare(strict_types=1);

use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityRegistryUpdate;
use App\Enums\Entities\EntityProfileField;
use App\Models\Entities\Entity;
use App\Support\ValueObjects\LicenseNumber;
use App\Support\ValueObjects\WebsiteUrl;
use Carbon\CarbonImmutable;

function profileOf(Entity $entity, EntityRegistryUpdate $registry): EntityProfileData
{
    return new EntityProfileData(
        $entity->name, $entity->entity_type, $entity->development_sector, $entity->org_size, 1999, $entity->region_id, $entity->city_id,
        $entity->governance_grade, $entity->employees_count, $entity->annual_beneficiaries_count, $entity->has_strategic_plan, $registry,
    );
}

it('accepts licence numbers of 3 to 20 Western digits only', function (string $value, bool $valid): void {
    $build = fn (): LicenseNumber => new LicenseNumber($value);

    $valid ? expect($build()->value)->toBe($value) : expect($build)->toThrow(InvalidArgumentException::class);
})->with([
    'three digits' => ['123', true],
    'leading zeros' => ['000123', true],
    'twenty digits' => [str_repeat('9', 20), true],
    'two digits' => ['12', false],
    'twenty-one digits' => [str_repeat('9', 21), false],
    'letters' => ['SA123', false],
    'Arabic-Indic digits' => ['١٢٣', false],
]);

it('normalises a public https website', function (string $input, string $normalised): void {
    expect(new WebsiteUrl($input)->value)->toBe($normalised);
})->with([
    'host only with a slash' => ['https://www.alamal.example.sa/', 'https://www.alamal.example.sa'],
    'upper case scheme and host' => ['HTTPS://WWW.Alamal.Example.SA/Programs?lang=ar#top', 'https://www.alamal.example.sa/Programs?lang=ar'],
    'default port' => ['https://alamal.example.sa:443/about/', 'https://alamal.example.sa/about/'],
    'surrounding spaces' => ['  https://alamal.example.sa  ', 'https://alamal.example.sa'],
    'punycode host' => ['https://xn--mgbaam7a8h.xn--mgberp4a5d4ar', 'https://xn--mgbaam7a8h.xn--mgberp4a5d4ar'],
]);

it('refuses anything but a public https website', function (string $input): void {
    expect(WebsiteUrl::tryFrom($input))->toBeNull()
        ->and(fn (): WebsiteUrl => new WebsiteUrl($input))->toThrow(InvalidArgumentException::class);
})->with([
    'http' => ['http://alamal.example.sa'],
    'script' => ['javascript:alert(1)'],
    'credentials' => ['https://owner:secret@alamal.example.sa'],
    'another port' => ['https://alamal.example.sa:8443'],
    'IP address' => ['https://192.168.1.10'],
    'single label host' => ['https://intranet'],
    'markup' => ['https://alamal.example.sa/"><script>'],
    'space inside' => ['https://alamal example.sa'],
    'too long' => ['https://alamal.example.sa/'.str_repeat('a', 240)],
]);

it('lists only the registry details the client sent', function (): void {
    $registry = new EntityRegistryUpdate(new LicenseNumber('770000123'), null, new WebsiteUrl('https://alamal.example.sa'), [EntityProfileField::LicenseNumber, EntityProfileField::FoundedOn]);

    expect($registry->toAttributes())->toBe(['license_number' => '770000123', 'founded_on' => null])
        ->and(new EntityRegistryUpdate()->toAttributes())->toBe([]);
});

it('keeps the founding year on the founding date that is sent or already stored', function (): void {
    $entity = Entity::factory()->create(['founding_year' => 2012]);

    $sent = $entity->profileAttributesFrom(profileOf($entity, new EntityRegistryUpdate(foundedOn: CarbonImmutable::parse('2015-06-01'), provided: [EntityProfileField::FoundedOn])));
    $stored = $entity->profileAttributesFrom(profileOf($entity, new EntityRegistryUpdate));
    $cleared = $entity->profileAttributesFrom(profileOf($entity, new EntityRegistryUpdate(provided: [EntityProfileField::FoundedOn])));

    expect($sent['founding_year'])->toBe(2015)
        ->and($stored['founding_year'])->toBe(2012)
        ->and($stored)->not->toHaveKey('founded_on')
        ->and($cleared['founding_year'])->toBe(1999)
        ->and($cleared['founded_on'])->toBeNull();
});
