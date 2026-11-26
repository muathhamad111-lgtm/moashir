<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Identity\ApplyNewPassword;
use App\Data\Demo\DemoAccount;
use App\Data\Demo\DemoUserAccount;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\Gender;
use App\Enums\Identity\Locale;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Enums\Platform\DemoRecordKind;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Identity\User;
use App\Models\Identity\UserAssessorQualification;
use Illuminate\Support\Facades\Date;

final readonly class DemoStaffInstaller
{
    private const array STAFF = [
        ['role' => RoleKey::SystemAdmin, 'name' => 'سارة العتيبي', 'gender' => Gender::Female, 'email' => 'demo.system.admin@moashir.example.sa'],
        ['role' => RoleKey::CriteriaOfficer, 'name' => 'فهد القحطاني', 'gender' => Gender::Male, 'email' => 'demo.criteria.officer@moashir.example.sa'],
        ['role' => RoleKey::RequestFollower, 'name' => 'نورة الشهري', 'gender' => Gender::Female, 'email' => 'demo.requests.follower@moashir.example.sa'],
        ['role' => RoleKey::Assessor, 'name' => 'عبدالله الدوسري', 'gender' => Gender::Male, 'email' => 'demo.assessor@moashir.example.sa'],
        ['role' => RoleKey::FilteringOfficer, 'name' => 'ريم الحربي', 'gender' => Gender::Female, 'email' => 'demo.filtering.officer@moashir.example.sa'],
        ['role' => RoleKey::AwardSecretary, 'name' => 'خالد الغامدي', 'gender' => Gender::Male, 'email' => 'demo.award.secretary@moashir.example.sa'],
        ['role' => RoleKey::ContentOfficer, 'name' => 'هند الزهراني', 'gender' => Gender::Female, 'email' => 'demo.content.officer@moashir.example.sa'],
        ['role' => RoleKey::FinanceOfficer, 'name' => 'ماجد السبيعي', 'gender' => Gender::Male, 'email' => 'demo.finance.officer@moashir.example.sa'],
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
            $installed[$member['role']->value] = $this->createMember($member);
        }

        return $installed;
    }

    /**
     * @param  array{role: RoleKey, name: string, gender: Gender, email: string}  $member
     */
    private function createMember(array $member): DemoUserAccount
    {
        ['role' => $role, 'name' => $name, 'gender' => $gender, 'email' => $email] = $member;

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw DemoDataRefused::missingPrerequisite(sprintf('the demo address of the %s account is already used', $role->value));
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'locale' => Locale::Arabic,
            'gender' => $gender,
            'status' => UserStatus::Active,
            'mfa_required' => true,
            'email_verified_at' => Date::now(),
        ])->save();
        $user->syncRoles([$role->value]);
        $this->qualifyAssessor($user, $role);

        $password = $this->passwords->generate();
        $this->applyPassword->handle($user, $password->reveal());
        $this->records->remember(DemoRecordKind::User, $user->id);

        return new DemoUserAccount($user, new DemoAccount($role->value, $name, $email, $password, mfaRequired: true));
    }

    private function qualifyAssessor(User $user, RoleKey $role): void
    {
        if ($role !== RoleKey::Assessor) {
            return;
        }

        foreach (AssessorQualification::cases() as $qualification) {
            UserAssessorQualification::query()->create(['user_id' => $user->id, 'qualification' => $qualification]);
        }
    }
}
