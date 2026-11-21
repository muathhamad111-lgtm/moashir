<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array PREVIOUS_TYPES = ['general', 'self_assessment', 'accredited_assessment', 'payments', 'technical_support', 'other'];

    private const string NEW_TYPE = 'privacy_request';

    public function up(): void
    {
        $this->replaceTypeCheck([...self::PREVIOUS_TYPES, self::NEW_TYPE]);
    }

    public function down(): void
    {
        DB::table('contact_messages')->where('enquiry_type', self::NEW_TYPE)->update(['enquiry_type' => 'other']);
        $this->replaceTypeCheck(self::PREVIOUS_TYPES);
    }

    /**
     * @param  list<string>  $types
     */
    private function replaceTypeCheck(array $types): void
    {
        $quoted = implode(', ', array_map(static fn (string $type): string => "'".$type."'", $types));

        DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_enquiry_type_check');
        DB::statement(sprintf('ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_enquiry_type_check CHECK (enquiry_type IN (%s))', $quoted));
    }
};
