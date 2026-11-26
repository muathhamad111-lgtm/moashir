<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\PhoneIndexRebuild;
use App\Enums\Identity\BlindIndexPurpose;
use App\Support\ScalarValue;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PhoneIndexRebuilder
{
    private const int CHUNK = 500;

    public function __construct(private BlindIndex $blindIndex, private Encrypter $encrypter) {}

    public function rebuild(): PhoneIndexRebuild
    {
        $indexed = 0;
        $unreadable = 0;

        DB::table('users')->whereNotNull('phone')->select(['id', 'phone'])->orderBy('id')
            ->chunkById(self::CHUNK, function (Collection $rows) use (&$indexed, &$unreadable): void {
                foreach ($rows as $row) {
                    $index = $this->indexOf(ScalarValue::string($row->phone));
                    DB::table('users')->where('id', ScalarValue::int($row->id))->update(['phone_index' => $index]);
                    $index === null ? $unreadable++ : $indexed++;
                }
            });

        DB::table('users')->whereNull('phone')->whereNotNull('phone_index')->update(['phone_index' => null]);

        return new PhoneIndexRebuild($indexed, $unreadable);
    }

    private function indexOf(string $encrypted): ?string
    {
        try {
            $phone = $this->encrypter->decrypt($encrypted, false);
        } catch (DecryptException) {
            return null;
        }

        return is_string($phone) && $phone !== '' ? $this->blindIndex->of(BlindIndexPurpose::Phone, $phone) : null;
    }
}
