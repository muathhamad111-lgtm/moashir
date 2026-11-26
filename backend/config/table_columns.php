<?php

declare(strict_types=1);

// The list tables whose columns a user may show or hide, and the columns each one offers. The SPA's
// own list (src/constants/tableColumns.js) must match it; a SPA test compares the two.
return [
    'tables' => json_decode((string) file_get_contents(resource_path('data/table-columns.json')), true, flags: JSON_THROW_ON_ERROR),
    // A user's choices for one table hold at most every column it offers; this caps the request.
    'max_tables' => 64,
];
