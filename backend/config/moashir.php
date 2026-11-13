<?php

declare(strict_types=1);

return [

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    'release' => env('APP_RELEASE'),

    'demo' => [
        'enabled' => env('APP_DEMO_DATA', false) === true,
        'posture' => env('MOASHIR_DEMO', false) === true,
    ],

    'super_admin' => [
        'initial_email' => env('SUPER_ADMIN_INITIAL_EMAIL'),
        'max_accounts' => (int) env('SUPER_ADMIN_MAX_ACCOUNTS', 2),
    ],

    'security' => [
        'alert_mail' => env('SECURITY_ALERT_MAIL'),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'health_allowed_ips' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('HEALTH_ALLOWED_IPS', ''))))),
    ],

    'audit' => [
        'export_max_rows' => 10000,
    ],

    'health' => [
        'queue_heartbeat_max_age_seconds' => 300,
        'min_free_disk_mb' => (int) (env('HEALTH_MIN_FREE_DISK_MB') ?: 1024),
    ],

    'auth' => [
        'otp' => [
            'length' => 6,
            'expiry_seconds' => (int) env('AUTH_OTP_EXPIRY_SECONDS', 300),
            'resend_cooldown_seconds' => (int) env('AUTH_OTP_RESEND_COOLDOWN_SECONDS', 60),
            'max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
            'per_user_per_ten_minutes' => 5,
            'per_user_per_day' => 20,
            'request_per_identifier_per_ten_minutes' => 3,
            'request_per_ip_per_ten_minutes' => 10,
            'sign_in_sms_per_hour' => 100,
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
        'summary_max_chars' => 2000,
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
        'logo' => [
            'max_kb' => 2048,
            'min_side_px' => 200,
            'max_side_px' => 2000,
        ],
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
        'bank_transfer' => [
            'max_active_accounts' => 10,
            'bulk_decision_max' => 50,
        ],
        'checkout_script' => [
            'url' => env('MOYASAR_FORM_SCRIPT_URL') ?: 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.js',
            'integrity' => env('MOYASAR_FORM_SCRIPT_INTEGRITY') ?: null,
        ],
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

    'insights' => [
        'results_per_page' => 15,
        'maturity_text_max_chars' => 600,
        'board_report_quote_max_chars' => 200,
        'board_report_summary_max_chars' => 600,
        'board_report_one_page_mains' => 12,
    ],

    'documents' => [
        'pdf_renderer' => in_array(env('PDF_RENDERER'), ['fake', 'chromium'], true) ? env('PDF_RENDERER') : 'unavailable',
        'chrome_binary' => env('PDF_CHROME_BINARY') ?: null,
        'render_timeout_seconds' => (int) (env('PDF_RENDER_TIMEOUT_SECONDS') ?: 60),
        'browser_startup_timeout_seconds' => 20,
        'render_queue_wait_seconds' => 30,
        'render_slot_key' => 'documents:pdf-render',
        'download_ttl_minutes' => 5,
    ],

    'certificates' => [
        'verification_log_retention_days' => 365,
        'verification_code_length' => 28,
        'expiring_soon_days' => 30,
    ],
    'contact' => [
        'submissions_per_ip_per_ten_minutes' => 5,
        'submissions_per_ip_per_day' => 20,
        'submissions_per_email_per_hour' => 3,
        'follow_ups_per_user_per_ten_minutes' => 10,
        'retention_months_min' => 1,
        'retention_months_max' => 120,
    ],

    'cms' => [
        'public_cache_seconds' => 3600,
        'image_max_kb' => 5120,
        'meta_max_bytes' => 16384,
        'sections_per_page_max' => 40,
        'items_per_section_max' => 60,
    ],

    'kpis' => [
        'featured_max' => 6,
        'featured_min' => 3,
    ],

    'branding' => [
        'logo_max_kb' => 1024,
        'logo_max_side_px' => 4096,
        'minimum_contrast' => '4.5',
        'surfaces' => [
            'on_accent' => '#0a2325',
            'light_content' => '#f5f8f8',
            'dark_content' => '#0a2325',
        ],
        'palette' => [
            'brand_default' => ['fill' => '#98d8d3', 'text_on_light' => '#1d7470'],
            'emerald' => ['fill' => '#17c78e', 'text_on_light' => '#08704e'],
            'teal' => ['fill' => '#33d1c4', 'text_on_light' => '#0b7068'],
            'cyan' => ['fill' => '#3aafc9', 'text_on_light' => '#136f84'],
            'sky' => ['fill' => '#6cb8f5', 'text_on_light' => '#1c5f9a'],
            'lavender' => ['fill' => '#a9a3f7', 'text_on_light' => '#5146c9'],
            'amber' => ['fill' => '#f4a72a', 'text_on_light' => '#8a5a00'],
            'coral' => ['fill' => '#f98c6e', 'text_on_light' => '#a63d1f'],
            'lime' => ['fill' => '#a7db4f', 'text_on_light' => '#4d7314'],
        ],
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
