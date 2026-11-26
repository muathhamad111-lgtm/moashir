<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum AuthEventType: string
{
    case LoginSucceeded = 'login_success';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case Locked = 'locked';
    case Unlocked = 'unlocked';
    case MfaSucceeded = 'mfa_success';
    case MfaFailed = 'mfa_failed';
    case MfaEnrolled = 'mfa_enrolled';
    case MfaDisabled = 'mfa_disabled';
    case OtpSent = 'otp_sent';
    case OtpVerified = 'otp_verified';
    case OtpFailed = 'otp_failed';
    case OtpRequestIgnored = 'otp_request_ignored';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';
    case PasswordChanged = 'password_changed';
    case SessionRevoked = 'session_revoked';
    case SessionExpired = 'session_expired';
    case SuperAdminLogin = 'super_admin_login';
}
