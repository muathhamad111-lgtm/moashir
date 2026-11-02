<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Models\Entities\Entity;
use Illuminate\Support\LazyCollection;

final readonly class EntityExport
{
    /**
     * @param  LazyCollection<int, Entity>  $entities
     */
    public function __construct(
        public string $fileName,
        public int $rowCount,
        public LazyCollection $entities,
        public string $locale,
    ) {}
}
