<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum SettingGroup: string
{
    case Payment = 'payment';
    case Sms = 'sms';
    case Email = 'email';
    case Ai = 'ai';
    case Assessment = 'assessment';
    case Bag = 'bag';
    case AbandonedCarts = 'abandoned_carts';
    case Files = 'files';
    case Certificates = 'certificates';
    case Contact = 'contact';
    case Auth = 'auth';
    case Insights = 'insights';
    case Finance = 'finance';
    case Periodic = 'periodic';
}
