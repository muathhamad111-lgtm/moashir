<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

interface MetaField
{
    public function key(): string;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(string $attribute): array;

    /**
     * @return array<string, string>
     */
    public function attributeNames(string $attribute): array;
}
