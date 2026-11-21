<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Identity\User;
use App\Services\Demo\DemoContentSource;
use App\Services\Demo\DemoInstallGuard;
use App\Services\Demo\DemoPagesInstaller;
use App\Services\Demo\DemoRecords;
use Illuminate\Support\Facades\DB;

final readonly class RefreshDemoHomePage
{
    public function __construct(
        private DemoInstallGuard $guard,
        private DemoRecords $records,
        private DemoContentSource $source,
        private DemoPagesInstaller $pages,
    ) {}

    public function handle(): bool
    {
        $this->guard->assertMayInstallContent();
        $editor = $this->records->staffWithRole(RoleKey::ContentOfficer) ?? $this->records->staffWithRole(RoleKey::SystemAdmin);

        if (! $editor instanceof User) {
            throw DemoDataRefused::missingPrerequisite('no demo Content Officer or System Administrator account exists');
        }

        $content = $this->source->read();

        return DB::transaction(fn (): bool => $this->pages->replace($editor, $content, CmsPageKey::Home));
    }
}
