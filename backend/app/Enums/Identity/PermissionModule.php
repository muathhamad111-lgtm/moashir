<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum PermissionModule: string
{
    case Dashboard = 'dashboard';
    case Entity = 'entity';
    case Privacy = 'privacy';
    case Assessment = 'assessment';
    case Requests = 'requests';
    case Award = 'award';
    case Reports = 'reports';
    case Bag = 'bag';
    case Criteria = 'criteria';
    case Content = 'content';
    case Ai = 'ai';
    case Admin = 'admin';
    case Settings = 'settings';
    case Finance = 'finance';
}
