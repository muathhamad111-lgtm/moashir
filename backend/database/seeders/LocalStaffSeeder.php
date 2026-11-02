<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use Illuminate\Database\Seeder;

final class LocalStaffSeeder extends Seeder
{
    private const array STAFF = [
        ['name' => 'سارة العتيبي', 'email' => 'system.admin@moashir.example.sa', 'role' => RoleKey::SystemAdmin],
        ['name' => 'فهد القحطاني', 'email' => 'criteria.officer@moashir.example.sa', 'role' => RoleKey::CriteriaOfficer],
        ['name' => 'نورة الشهري', 'email' => 'requests.follower@moashir.example.sa', 'role' => RoleKey::RequestFollower],
        ['name' => 'عبدالله الدوسري', 'email' => 'assessor@moashir.example.sa', 'role' => RoleKey::Assessor],
        ['name' => 'ريم الحربي', 'email' => 'filtering.officer@moashir.example.sa', 'role' => RoleKey::FilteringOfficer],
        ['name' => 'خالد الغامدي', 'email' => 'award.secretary@moashir.example.sa', 'role' => RoleKey::AwardSecretary],
        ['name' => 'هند الزهراني', 'email' => 'content.officer@moashir.example.sa', 'role' => RoleKey::ContentOfficer],
        ['name' => 'ماجد السبيعي', 'email' => 'finance.officer@moashir.example.sa', 'role' => RoleKey::FinanceOfficer],
    ];

    public function run(): void
    {
        foreach (self::STAFF as $member) {
            $user = User::query()->firstOrNew(['email' => $member['email']]);
            $user->forceFill(['name' => $member['name'], 'status' => 'active', 'mfa_required' => true, 'email_verified_at' => now()])->save();
            $user->syncRoles([$member['role']->value]);
        }
    }
}
