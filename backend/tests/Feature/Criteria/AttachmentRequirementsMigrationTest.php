<?php

declare(strict_types=1);

use App\Models\Criteria\AttachmentRequirement;
use Illuminate\Support\Facades\DB;

function attachmentRequirementsMigration(): object
{
    return require database_path('migrations/2026_11_20_110000_extend_attachment_requirements.php');
}

it('rolls back to sub and indicator owners only and comes back with the main owner', function (): void {
    $draft = balancedDraft();
    mainOf($draft, 'M1')->attachmentRequirements()->create(['key' => 'plan', 'name' => ['ar' => 'الخطة', 'en' => 'Plan'], 'is_required' => true, 'sort_order' => 1]);
    subOf($draft, 'M1.1')->attachmentRequirements()->create(['key' => 'charter', 'name' => ['ar' => 'اللائحة', 'en' => 'Charter'], 'is_required' => true, 'sort_order' => 1]);

    $migration = attachmentRequirementsMigration();
    $migration->down();

    expect(DB::table('attachment_requirements')->pluck('key')->all())->toBe(['charter']);

    $migration->up();
    mainOf($draft, 'M1')->attachmentRequirements()->create(['key' => 'plan', 'name' => ['ar' => 'الخطة', 'en' => 'Plan'], 'is_required' => true, 'sort_order' => 1, 'max_files' => 3]);

    expect(AttachmentRequirement::query()->where('key', 'plan')->value('max_files'))->toBe(3);
});
