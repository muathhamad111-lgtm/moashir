<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

/**
 * How a row of the e-mail's details card is set: a plain value, a deadline in the warning colour, a larger amount,
 * or a reference number kept left to right inside Arabic text.
 */
enum MailDetailTone: string
{
    case Plain = 'plain';
    case Warning = 'warning';
    case Strong = 'strong';
    case Reference = 'reference';
}
