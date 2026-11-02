<?php

declare(strict_types=1);

namespace App\Enums\Search;

enum SearchResultType: string
{
    case Entities = 'entities';
    case Requests = 'requests';
    case Users = 'users';
}
