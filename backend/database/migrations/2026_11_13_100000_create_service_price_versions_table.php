<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'purpose' => "purpose IN ('self', 'accredited', 'award', 'bag')",
        'availability' => "availability IN ('available', 'paused', 'hidden')",
        'price' => 'price_before_vat >= 0',
        'cancelled' => 'cancelled_by_user_id IS NULL OR cancelled_at IS NOT NULL',
    ];

    public function up(): void
    {
        Schema::create('service_price_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose', 20);
            $table->decimal('price_before_vat', 10, 2);
            $table->string('availability', 10);
            $table->timestampTz('effective_from');
            $table->foreignId('previous_version_id')->nullable()->constrained('service_price_versions')->restrictOnDelete();
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->index(['purpose', 'effective_from']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE service_price_versions ADD CONSTRAINT service_price_versions_%s_check CHECK (%s)', $name, $expression));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_price_versions');
    }
};
