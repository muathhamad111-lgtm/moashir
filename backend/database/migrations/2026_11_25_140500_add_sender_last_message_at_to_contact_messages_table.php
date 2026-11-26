<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The time of the sender's latest message, kept on the message so the derived inbox status
 * (App\Enums\Contact\ContactInboxStatus) filters and counts with plain indexed conditions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->timestampTz('sender_last_message_at')->nullable()->after('replied_at')->index();
        });

        DB::statement(<<<'SQL'
            UPDATE contact_messages SET sender_last_message_at = latest.created_at
            FROM (
                SELECT contact_message_id, max(created_at) AS created_at
                FROM contact_message_entries WHERE kind = 'message' GROUP BY contact_message_id
            ) AS latest
            WHERE latest.contact_message_id = contact_messages.id
            SQL);
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropIndex(['sender_last_message_at']);
            $table->dropColumn('sender_last_message_at');
        });
    }
};
