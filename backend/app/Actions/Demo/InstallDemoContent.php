<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Data\Demo\DemoContent;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Identity\User;
use App\Services\Demo\DemoContentInstaller;
use App\Services\Demo\DemoInstallGuard;
use App\Services\Demo\DemoRecords;
use Illuminate\Support\Facades\DB;

final readonly class InstallDemoContent
{
    public function __construct(
        private DemoInstallGuard $guard,
        private DemoRecords $records,
        private DemoContentInstaller $content,
    ) {}

    public function handle(): DemoContent
    {
        $this->guard->assertMayInstallContent();
        $editor = $this->records->staffWithRole(RoleKey::ContentOfficer) ?? $this->records->staffWithRole(RoleKey::SystemAdmin);

        if (! $editor instanceof User) {
            throw DemoDataRefused::missingPrerequisite('no demo Content Officer or System Administrator account exists');
        }

        return DB::transaction(fn (): DemoContent => $this->content->install($editor));
    }
}
