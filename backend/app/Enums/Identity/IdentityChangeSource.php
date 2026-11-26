<?php

declare(strict_types=1);

namespace App\Enums\Identity;

/**
 * How a value in the identity history came to be recorded.
 */
enum IdentityChangeSource: string
{
    case ApprovedRequest = 'approved_request';
    case AdminEntry = 'admin_entry';
    case SuperAdminEntry = 'super_admin_entry';
    case EntityRegistration = 'entity_registration';
    case InvitationAcceptance = 'invitation_acceptance';
}
