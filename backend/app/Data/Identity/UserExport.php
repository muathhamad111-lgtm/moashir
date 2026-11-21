<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Models\Identity\User;
use Illuminate\Support\LazyCollection;

final readonly class UserExport
{
    /**
     * @param  LazyCollection<int, User>  $users
     */
    public function __construct(
        public string $fileName,
        public int $rowCount,
        public LazyCollection $users,
        public string $locale,
    ) {}
}
