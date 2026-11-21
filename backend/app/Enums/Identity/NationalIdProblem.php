<?php

declare(strict_types=1);

namespace App\Enums\Identity;

/**
 * The first rule a national ID or Iqama number breaks, in the order the operator listed them.
 */
enum NationalIdProblem: string
{
    case NotDigits = 'digits';
    case WrongLength = 'length';
    case WrongPrefix = 'prefix';

    public function messageKey(): string
    {
        return 'validation.custom.national_id.'.$this->value;
    }
}
