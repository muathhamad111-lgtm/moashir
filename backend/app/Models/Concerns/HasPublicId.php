<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(static function (Model $model): void {
            if ($model->getAttribute('public_id') === null) {
                $model->setAttribute('public_id', Str::uuid()->toString());
            }
        });
    }
}
