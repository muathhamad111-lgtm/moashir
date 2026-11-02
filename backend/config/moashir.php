<?php

declare(strict_types=1);

return [

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    'release' => env('APP_RELEASE'),

    'demo' => [
        'enabled' => env('APP_DEMO_DATA', false) === true,
    ],

    'super_admin' => [
        'initial_email' => env('SUPER_ADMIN_INITIAL_EMAIL'),
        'max_accounts' => (int) env('SUPER_ADMIN_MAX_ACCOUNTS', 2),
    ],

    'security' => [
        'alert_mail' => env('SECURITY_ALERT_MAIL'),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'health_allowed_ips' => array_values(array_filter(explode(',', (string) env('HEALTH_ALLOWED_IPS', '')))),
    ],

    'auth' => [
        'login_modes' => [
            'email_password' => (bool) env('AUTH_LOGIN_EMAIL_PASSWORD', true),
            'email_otp' => (bool) env('AUTH_LOGIN_EMAIL_OTP', true),
            'sms_otp' => (bool) env('AUTH_LOGIN_SMS_OTP', false),
        ],
        'otp' => [
            'length' => 6,
            'expiry_seconds' => (int) env('AUTH_OTP_EXPIRY_SECONDS', 300),
            'resend_cooldown_seconds' => (int) env('AUTH_OTP_RESEND_COOLDOWN_SECONDS', 60),
            'max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
        ],
        'lockout' => [
            'max_attempts' => (int) env('AUTH_LOCKOUT_MAX_ATTEMPTS', 5),
            'minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
        ],
        'password' => [
            'min_length' => 12,
            'max_length' => 128,
            'history' => 5,
        ],
        'invitation_ttl_hours' => (int) env('AUTH_INVITATION_TTL_HOURS', 72),
        'pending_authentication_ttl_minutes' => 10,
        'session' => [
            'idle_minutes' => [
                'super_admin' => (int) env('SESSION_IDLE_SUPER_ADMIN_MINUTES', 15),
                'staff' => (int) env('SESSION_IDLE_STAFF_MINUTES', 30),
                'entity' => (int) env('SESSION_IDLE_ENTITY_MINUTES', 60),
            ],
            'absolute_hours' => (int) env('SESSION_ABSOLUTE_HOURS', 12),
        ],
    ],

    'assessment' => [
        'scale_kind' => env('ASSESSMENT_SCALE_KIND', 'of_10'),
        'draft_notice_cooldown_minutes' => 60,
        'criteria_update_hold_stale_minutes' => 30,
        'criteria_update_redispatch_alert_after' => 3,
        'cv_max_chars' => 10000,
        'feedback_max_chars' => 5000,
        'completion_deadline_max_days' => 60,
        'evidence_bundle_sync_max_bytes' => 20 * 1024 * 1024,
        'evidence_bundle_ttl_hours' => 24,
        'sla_days_limits' => ['min' => 1, 'max' => 120],
        'open_paid_requests_after_minutes' => 10,
    ],

    'award' => [
        'submission_reminder_days' => [7, 3, 1],
        'candidates_limit' => 100,
    ],

    'entities' => [
        'email_change_policy' => env('ENTITY_EMAIL_CHANGE_POLICY') ?: 'requires_approval',
        'verification_reminder_cooldown_minutes' => (int) (env('ENTITY_VERIFICATION_REMINDER_COOLDOWN_MINUTES') ?: 15),
        'export_max_rows' => 10000,
    ],

    'sms' => [
        'driver' => env('SMS_PROVIDER') === 'fake' ? 'fake' : 'settings',
    ],

    'finance' => [
        'order_validity_minutes' => ['min' => 5, 'max' => 60],
        'pending_grace_minutes' => 15,
        'reconcile_after_minutes' => 5,
        'webhook_redispatch_after_minutes' => 10,
        'export_sync_max_rows' => 5000,
        'download_ttl_minutes' => 5,
        'webhook_max_bytes' => 262144,
        'webhooks_per_minute' => 120,
    ],

    'files' => [
        'disk' => env('FILES_DISK', 'local'),
        'scanner' => env('FILES_SCANNER') === 'fake' ? 'fake' : 'clamav',
        'scan_retry_after_minutes' => 10,
        'orphan_grace_hours' => (int) (env('FILES_ORPHAN_GRACE_HOURS') ?: 24),
        'clamav' => [
            'socket' => env('FILES_CLAMAV_SOCKET', 'unix:///var/run/clamav/clamd.ctl'),
            'timeout_seconds' => (int) env('FILES_CLAMAV_TIMEOUT_SECONDS', 30),
        ],
    ],

    'documents' => [
        'pdf_renderer' => env('PDF_RENDERER') === 'fake' ? 'fake' : 'unavailable',
    ],

    'integrations' => [
        'env_fallback' => (bool) env('INTEGRATIONS_ENV_FALLBACK', false),
        'egress_ips' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SERVER_EGRESS_IPS', ''))))),
        'probe_per_minute' => 3,
        'alert_cooldown_minutes' => 10,
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => [
            'payment' => 15,
            'sms' => 15,
            'ai_catalog' => 15,
            'ai_answer' => 30,
            'reachability' => 10,
        ],
        'apple_pay_association_max_bytes' => 65536,
        'environment' => [
            'payment' => [
                'mode' => env('MOYASAR_ENV'),
                'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
                'secret_key' => env('MOYASAR_SECRET_KEY'),
                'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
            ],
            'sms' => [
                'provider' => env('SMS_PROVIDER'),
                'taqnyat' => [
                    'bearer_token' => env('TAQNYAT_API_TOKEN'),
                    'sender_name' => env('TAQNYAT_SENDER'),
                ],
                'msegat' => [
                    'username' => env('MSEGAT_USERNAME'),
                    'api_key' => env('MSEGAT_API_KEY'),
                    'sender_name' => env('MSEGAT_SENDER'),
                ],
            ],
            'ai' => [
                'provider' => env('AI_PROVIDER'),
                'api_key' => env('AI_API_KEY'),
                'model' => env('AI_MODEL'),
            ],
        ],
    ],

];
