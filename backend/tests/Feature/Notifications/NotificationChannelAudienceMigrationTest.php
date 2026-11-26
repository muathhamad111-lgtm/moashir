<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function channelAudienceMigration(): object
{
    return require database_path('migrations/2026_11_22_200000_add_audience_to_notification_channel_settings_table.php');
}

/**
 * @return list<string>
 */
function channelSettingRows(): array
{
    return DB::table('notification_channel_settings')->orderBy('code')->orderBy('channel')->get()
        ->map(static fn (object $row): string => implode('|', array_filter([$row->code, $row->channel, $row->audience ?? null, $row->is_enabled ? 'on' : 'off'])))
        ->all();
}

it('copies every existing channel choice to both audiences and back to one per type and channel', function (): void {
    $migration = channelAudienceMigration();
    $migration->down();
    $editor = systemAdmin()->id;
    DB::table('notification_channel_settings')->insert([
        ['code' => 'STD-04', 'channel' => 'email', 'is_enabled' => false, 'updated_by_user_id' => $editor, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'REG-01', 'channel' => 'sms', 'is_enabled' => true, 'updated_by_user_id' => $editor, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(channelSettingRows())->toBe([
        'REG-01|sms|beneficiary|on',
        'REG-01|sms|management|on',
        'STD-04|email|beneficiary|off',
        'STD-04|email|management|off',
    ])->and(fn () => DB::transaction(static fn () => DB::table('notification_channel_settings')->insert(['code' => 'STD-04', 'channel' => 'email', 'audience' => 'management', 'is_enabled' => true])))
        ->toThrow(UniqueConstraintViolationException::class);

    $migration->down();
    expect(channelSettingRows())->toBe(['REG-01|sms|on', 'STD-04|email|off']);

    $migration->up();
});
