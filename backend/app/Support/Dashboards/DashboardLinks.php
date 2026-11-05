<?php

declare(strict_types=1);

namespace App\Support\Dashboards;

final class DashboardLinks
{
    public const string ENTITIES = '/admin/entities';

    public const string ENTITY_EMAIL_CHANGES = '/admin/entities/email-changes';

    public const string PRIVACY_REQUESTS = '/admin/privacy-requests';

    public const string USERS = '/admin/settings/users';

    public const string CYCLES = '/admin/award/cycles';

    public const string REQUESTS = '/admin/requests';

    public const string ASSIGNED_REQUESTS = '/admin/requests/assigned';

    public const string COMMITTEE_ASSIGNMENTS = '/admin/committee/assignments';

    public const string COMMITTEE_DISCUSSIONS = '/admin/committee/discussions';

    public const string FILTERING = '/admin/award/filtering';

    public const string SECRETARY = '/admin/award/secretary';

    public const string RESULTS = '/admin/award/results';

    public const string FINANCE = '/finance';

    public const string FINANCE_TRANSACTIONS = '/finance/transactions';

    public const string FINANCE_REFUNDS = '/finance/refunds';

    public const string CRITERIA = '/admin/settings/criteria';

    public const string HOMEPAGE = '/admin/settings/homepage';

    public const string KPIS = '/admin/kpis';

    public const string CONTACT_MESSAGES = '/admin/contact-messages';

    public const string ENTITY_PROFILE = '/entity/profile';

    public const string ASSESSMENT_HISTORY = '/assessment/history';

    public const string SELF_ASSESSMENT = '/assessment/self';

    public const string ACCREDITED_ASSESSMENT = '/assessment/accredited';

    public const string ADVANCED_ASSESSMENT = '/assessment/advanced';

    public const string MY_PAYMENTS = '/payments';

    public const string MY_MESSAGES = '/messages';

    public const string NOTIFICATIONS = '/notifications';

    public static function requestsWithStatus(string ...$statuses): string
    {
        return self::REQUESTS.'?'.http_build_query(['status' => implode(',', $statuses)]);
    }

    public static function newContactMessages(): string
    {
        return self::CONTACT_MESSAGES.'?'.http_build_query(['status' => 'new']);
    }

    public static function overdueRequests(): string
    {
        return self::REQUESTS.'?'.http_build_query(['overdue' => 'true']);
    }
}
