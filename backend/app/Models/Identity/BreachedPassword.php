<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $digest
 */
final class BreachedPassword extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'digest';

    protected $keyType = 'string';

    protected $fillable = ['digest'];
}
