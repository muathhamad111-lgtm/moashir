<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Identity\ApplyNewPassword;
use App\Data\Demo\DemoAccount;
use App\Data\Demo\DemoUserAccount;
use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Enums\Platform\DemoRecordKind;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;

final readonly class DemoStaffInstaller
{
    private const array STAFF = [
        ['role' => RoleKey::SystemAdmin, 'name' => 'سارة العتيبي', 'email' => 'demo.system.admin@moashir.example.sa'],
        ['role' => RoleKey::CriteriaOfficer, 'name' => 'فهد القحطاني', 'email' => 'demo.criteria.officer@moashir.example.sa'],
        ['role' => RoleKey::RequestFollower, 'name' => 'نورة الشهري', 'email' => 'demo.requests.follower@moashir.example.sa'],
        ['role' => RoleKey::Assessor, 'name' => 'عبدالله الدوسري', 'email' => 'demo.assessor@moashir.example.sa'],
        ['role' => RoleKey::FilteringOfficer, 'name' => 'ريم الحربي', 'email' => 'demo.filtering.officer@moashir.example.sa'],
        ['role' => RoleKey::AwardSecretary, 'name' => 'خالد الغامدي', 'email' => 'demo.award.secretary@moashir.example.sa'],
        ['role' => RoleKey::ContentOfficer, 'name' => 'هند الزهراني', 'email' => 'demo.content.officer@moashir.example.sa'],
        ['role' => RoleKey::FinanceOfficer, 'name' => 'ماجد السبيعي', 'email' => 'demo.finance.officer@moashir.example.sa'],
    ];

    public function __construct(
        private ApplyNewPassword $applyPassword,
        private DemoPasswordGenerator $passwords,
        private DemoRecords $records,
    ) {}

    /**
     * @return array<string, DemoUserAccount>
     */
    public function install(): array
    {
        $installed = [];

        foreach (self::STAFF as $member) {
            $installed[$member['role']->value] = $this->createMember($member['role'], $member['name'], $member['email']);
        }

        return $installed;
    }

    private function createMember(RoleKey $role, string $name, string $email): DemoUserAccount
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw DemoDataRefused::missingPrerequisite(sprintf('the demo address of the %s account is already used', $role->value));
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'locale' => Locale::Arabic,
            'status' => UserStatus::Active,
            'mfa_required' => true,
            'email_verified_at' => Date::now(),
        ])->save();
        $user->syncRoles([$role->value]);

        $password = $this->passwords->generate();
        $this->applyPassword->handle($user, $password->reveal());
        $this->records->remember(DemoRecordKind::User, $user->id);

        return new DemoUserAccount($user, new DemoAccount($role->value, $name, $email, $password, mfaRequired: true));
    }
}
