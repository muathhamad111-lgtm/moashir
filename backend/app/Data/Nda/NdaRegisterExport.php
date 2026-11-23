<?php

declare(strict_types=1);

namespace App\Data\Nda;

use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class NdaRegisterExport
{
    /**
     * @param  Builder<User>  $users
     */
    public function __construct(
        public string $fileName,
        public int $rowCount,
        public TermsVersion $version,
        public Builder $users,
        public string $locale,
    ) {}
}
