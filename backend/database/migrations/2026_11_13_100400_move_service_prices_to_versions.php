<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array MIGRATED_SETTINGS = [
        'accredited' => ['key' => 'assessment.accredited_price', 'group' => 'assessment'],
        'bag' => ['key' => 'bag.price', 'group' => 'bag'],
    ];

    private const array FREE_BY_DEFAULT = ['self', 'award'];

    private const string LINKED_PATHS = 'payment.linked_paths';

    private const array NEW_LINKED_PATHS = ['self', 'award'];

    private const string PRICE_PATTERN = '/^\d{1,7}\.\d{2}$/';

    public function up(): void
    {
        $now = CarbonImmutable::now();

        foreach (self::MIGRATED_SETTINGS as $purpose => $setting) {
            $price = $this->storedPrice($setting['key']);

            if ($price !== null && ! $this->hasVersion($purpose)) {
                $this->insertVersion($purpose, $price, $now);
            }

            DB::table('settings')->where('key', $setting['key'])->delete();
        }

        foreach (self::FREE_BY_DEFAULT as $purpose) {
            if (! $this->hasVersion($purpose)) {
                $this->insertVersion($purpose, '0.00', $now);
            }
        }

        $this->rewriteLinkedPaths(static fn (array $paths): array => array_values(array_unique([...$paths, ...self::NEW_LINKED_PATHS])));
    }

    public function down(): void
    {
        $now = CarbonImmutable::now();

        foreach (self::MIGRATED_SETTINGS as $purpose => $setting) {
            $price = $this->priceInForce($purpose, $now);

            if ($price !== null && (float) $price > 0) {
                DB::table('settings')->updateOrInsert(['key' => $setting['key']], [
                    'group' => $setting['group'],
                    'type' => 'decimal',
                    'value' => json_encode($price),
                    'is_public' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('service_price_versions')->delete();
        $this->rewriteLinkedPaths(static fn (array $paths): array => array_values(array_diff($paths, self::NEW_LINKED_PATHS)));
    }

    private function storedPrice(string $key): ?string
    {
        $raw = DB::table('settings')->where('key', $key)->value('value');
        $value = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_string($value) || preg_match(self::PRICE_PATTERN, $value) !== 1 || (float) $value <= 0) {
            return null;
        }

        return $value;
    }

    private function hasVersion(string $purpose): bool
    {
        return DB::table('service_price_versions')->where('purpose', $purpose)->exists();
    }

    private function insertVersion(string $purpose, string $price, CarbonImmutable $now): void
    {
        DB::table('service_price_versions')->insert([
            'purpose' => $purpose,
            'price_before_vat' => $price,
            'availability' => 'available',
            'effective_from' => $now,
            'created_at' => $now,
        ]);
    }

    private function priceInForce(string $purpose, CarbonImmutable $now): ?string
    {
        $price = DB::table('service_price_versions')
            ->where('purpose', $purpose)
            ->whereNull('cancelled_at')
            ->where('effective_from', '<=', $now)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('price_before_vat');

        return is_string($price) ? $price : null;
    }

    /**
     * @param  Closure(list<string>): list<string>  $rewrite
     */
    private function rewriteLinkedPaths(Closure $rewrite): void
    {
        $raw = DB::table('settings')->where('key', self::LINKED_PATHS)->value('value');

        if (! is_string($raw)) {
            return;
        }

        $paths = json_decode($raw, true);
        $current = is_array($paths) ? array_values(array_filter($paths, is_string(...))) : [];

        DB::table('settings')->where('key', self::LINKED_PATHS)->update(['value' => json_encode($rewrite($current)), 'updated_at' => CarbonImmutable::now()]);
    }
};
