<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Entities\VerifyEntityRegistration;
use App\Data\Demo\DemoAccount;
use App\Data\Demo\DemoUserAccount;
use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Data\Entities\EntityRegistrationData;
use App\Enums\Entities\DevelopmentSector;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Platform\DemoRecordKind;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Entities\City;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Services\Entities\EntityAccountCreator;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\EntityName;

final readonly class DemoEntityInstaller
{
    private const array ENTITIES = [
        [
            'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
            'type' => EntityType::CivilAssociation, 'sector' => DevelopmentSector::Social, 'size' => OrgSize::Medium,
            'founded' => 2009, 'grade' => '88.50', 'employees' => 42, 'beneficiaries' => 12500,
            'region' => 'riyadh', 'city' => 'riyadh',
            'owner' => 'عبدالرحمن المطيري', 'email' => 'demo.alamal@moashir.example.sa',
        ],
        [
            'name' => ['ar' => 'مؤسسة النماء الأهلية', 'en' => 'Al-Namaa Civil Foundation'],
            'type' => EntityType::CivilFoundation, 'sector' => DevelopmentSector::Educational, 'size' => OrgSize::Small,
            'founded' => 2015, 'grade' => '76.00', 'employees' => 18, 'beneficiaries' => 3400,
            'region' => 'makkah', 'city' => 'jeddah',
            'owner' => 'منيرة الشمري', 'email' => 'demo.alnamaa@moashir.example.sa',
        ],
        [
            'name' => ['ar' => 'وقف الخير التنموي', 'en' => 'Al-Khair Development Waqf'],
            'type' => EntityType::Waqf, 'sector' => DevelopmentSector::Developmental, 'size' => OrgSize::Large,
            'founded' => 2001, 'grade' => '91.25', 'employees' => 120, 'beneficiaries' => 40000,
            'region' => 'eastern', 'city' => 'dammam',
            'owner' => 'سلطان العنزي', 'email' => 'demo.alkhair@moashir.example.sa',
        ],
    ];

    public function __construct(
        private EntityAccountCreator $creator,
        private VerifyEntityRegistration $verify,
        private DemoPasswordGenerator $passwords,
        private DemoRecords $records,
    ) {}

    /**
     * @param  list<int>  $termsVersionIds
     * @return list<DemoUserAccount>
     */
    public function install(array $termsVersionIds): array
    {
        $owners = [];

        foreach (self::ENTITIES as $definition) {
            $owners[] = $this->register($definition, $termsVersionIds);
        }

        return $owners;
    }

    /**
     * @param  array{name: array{ar: string, en: string}, type: EntityType, sector: DevelopmentSector, size: OrgSize, founded: int, grade: string, employees: int, beneficiaries: int, region: string, city: string, owner: string, email: string}  $definition
     * @param  list<int>  $termsVersionIds
     */
    private function register(array $definition, array $termsVersionIds): DemoUserAccount
    {
        if (User::withTrashed()->where('email', $definition['email'])->exists()) {
            throw DemoDataRefused::missingPrerequisite('a demo entity address is already used');
        }

        $password = $this->passwords->generate();
        $entity = $this->creator->create(new EntityRegistrationData(
            $this->profileOf($definition),
            new EntityContactData($definition['owner'], null),
            new EmailAddress($definition['email']),
            $password->reveal(),
            $termsVersionIds,
            false,
            Locale::Arabic,
        ));
        $owner = $entity->owner()->firstOrFail();
        $this->verify->handle($owner);

        $this->records->remember(DemoRecordKind::Entity, $entity->id);
        $this->records->remember(DemoRecordKind::User, $owner->id);

        return new DemoUserAccount($owner, new DemoAccount(RoleKey::Entity->value, $definition['owner'], $definition['email'], $password, mfaRequired: false));
    }

    /**
     * @param  array{name: array{ar: string, en: string}, type: EntityType, sector: DevelopmentSector, size: OrgSize, founded: int, grade: string, employees: int, beneficiaries: int, region: string, city: string, owner: string, email: string}  $definition
     */
    private function profileOf(array $definition): EntityProfileData
    {
        $region = Region::query()->where('code', $definition['region'])->first()
            ?? throw DemoDataRefused::missingPrerequisite('the regions are not installed (moashir:regions:install-seed)');
        $city = City::query()->where('region_id', $region->id)->where('code', $definition['city'])->first()
            ?? throw DemoDataRefused::missingPrerequisite('the cities are not installed (moashir:regions:install-seed)');

        return new EntityProfileData(
            new EntityName($definition['name']['ar'], $definition['name']['en']),
            $definition['type'],
            $definition['sector'],
            $definition['size'],
            $definition['founded'],
            $region->id,
            $city->id,
            $definition['grade'],
            $definition['employees'],
            $definition['beneficiaries'],
            true,
        );
    }
}
