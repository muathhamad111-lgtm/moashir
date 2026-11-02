<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20);
            $table->timestampTz('last_seen_at');
            $table->foreignId('converted_payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->timestampTz('contacted_at')->nullable();
            $table->timestampsTz();

            $table->index(['entity_id', 'purpose', 'created_at']);
            $table->index(['purpose', 'last_seen_at']);
        });

        DB::statement("ALTER TABLE checkout_intents ADD CONSTRAINT checkout_intents_purpose_check CHECK (purpose IN ('accredited', 'bag'))");
        DB::statement('CREATE UNIQUE INDEX checkout_intents_one_open ON checkout_intents (entity_id, purpose) WHERE converted_payment_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intents');
    }
};
