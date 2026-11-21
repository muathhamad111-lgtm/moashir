<?php

declare(strict_types=1);

use App\Models\Contact\ContactMessage;
use App\Models\Identity\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function dataSubjectRemoval(): object
{
    return require database_path('migrations/2026_11_15_110000_remove_data_subject_requests.php');
}

it('moves the personal-data enquiries to «other», drops the register and the permission', function (): void {
    $migration = dataSubjectRemoval();
    $migration->down();
    $message = contactMessage();
    DB::table('contact_messages')->where('id', $message->id)->update(['enquiry_type' => 'privacy_request']);
    DB::table('permissions')->insert(['name' => 'privacy.requests.manage', 'guard_name' => 'web', 'label' => json_encode(['ar' => 'x', 'en' => 'x']), 'module' => 'privacy', 'created_at' => now(), 'updated_at' => now()]);

    $migration->up();

    expect(ContactMessage::query()->findOrFail($message->id)->getRawOriginal('enquiry_type'))->toBe('other')
        ->and(Schema::hasTable('privacy_requests'))->toBeFalse()
        ->and(Permission::query()->where('name', 'privacy.requests.manage')->exists())->toBeFalse();
});

it('refuses the removed enquiry type afterwards', function (): void {
    $message = contactMessage();

    expect(fn () => DB::table('contact_messages')->where('id', $message->id)->update(['enquiry_type' => 'privacy_request']))
        ->toThrow(QueryException::class);
});

it('restores the schema when rolled back', function (): void {
    dataSubjectRemoval()->down();

    expect(Schema::hasTable('privacy_requests'))->toBeTrue();

    dataSubjectRemoval()->up();

    expect(Schema::hasTable('privacy_requests'))->toBeFalse();
});
