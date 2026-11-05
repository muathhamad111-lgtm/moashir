<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Files\PublicAssetRejected;
use App\Models\Files\StoredFile;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Model;

final readonly class PublicAssetAttacher
{
    public function resolve(string $publicId, StoredFilePurpose $purpose, Model $owner, string $field): StoredFile
    {
        $file = StoredFile::query()
            ->where('public_id', $publicId)
            ->ofPurpose($purpose)
            ->clean()
            ->lockForUpdate()
            ->first();

        if (! $file instanceof StoredFile || ! $this->isFreeFor($file, $owner)) {
            throw PublicAssetRejected::unusable($field);
        }

        return $file;
    }

    public function attach(StoredFile $file, Model $owner): void
    {
        if ($file->attachable_type === null) {
            $file->attachable()->associate($owner)->save();
        }
    }

    private function isFreeFor(StoredFile $file, Model $owner): bool
    {
        if ($file->attachable_type === null) {
            return true;
        }

        return $owner->exists
            && $file->attachable_type === $owner->getMorphClass()
            && $file->attachable_id === ScalarValue::int($owner->getKey());
    }
}
