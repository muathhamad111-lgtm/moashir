<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum CheckoutPattern: string
{
    case Intent = 'intent';
    case Form = 'form';
}
