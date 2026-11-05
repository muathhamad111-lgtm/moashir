const ADMIN = { kind: 'user', id: 1, name: 'ريم بنت خالد الحربي', roles: ['system_admin'] }
const CONTENT = {
  kind: 'user',
  id: 31,
  name: 'أ. هيفاء بنت سليمان العنزي',
  roles: ['content_officer'],
}
const SYSTEM = { kind: 'system', id: null, name: null, roles: [] }
const MASKED = { kind: 'super_admin', id: null, name: null, roles: ['super_admin'] }

function row(id, event, actor, subject, createdAt, extra = {}) {
  return {
    id,
    event,
    actor,
    subject,
    oldValues: extra.oldValues ?? null,
    newValues: extra.newValues ?? null,
    reason: extra.reason ?? null,
    tags: extra.tags ?? [],
    ipAddress: actor.kind === 'system' ? null : '10.20.30.40',
    userAgent: actor.kind === 'system' ? null : 'Mozilla/5.0 (Macintosh) Chrome/142',
    url: extra.url ?? null,
    requestId: `0d6f0f8e-3c1a-4e8a-9b8e-2b6f4c6d${String(id).padStart(4, '0')}`,
    createdAt,
  }
}

export const auditLogsFixture = [
  row(
    1846,
    'certificate.revoked',
    ADMIN,
    { type: 'certificate', id: 3 },
    '2026-11-04T10:15:00+00:00',
    {
      oldValues: { status: 'issued' },
      newValues: { status: 'revoked' },
      reason: 'أُلغيت الشهادة لثبوت خطأ في احتساب نتيجة المعيار الرابع.',
      url: 'https://moashir.example.sa/api/v1/certificates/[redacted]/revoke',
    },
  ),
  row(
    1845,
    'terms.published',
    CONTENT,
    { type: 'terms_version', id: 14 },
    '2026-11-04T09:20:05+00:00',
    {
      newValues: { version: '2026-11-draft', kind: 'privacy' },
    },
  ),
  row(1844, 'entity.updated', ADMIN, { type: 'entity', id: 6002 }, '2026-11-04T09:45:00+00:00', {
    oldValues: { status: 'active' },
    newValues: { status: 'deactivated' },
    reason: 'طلب مجلس إدارة الجهة إيقاف الحساب مؤقتًا.',
    url: 'https://moashir.example.sa/api/v1/entities/6002/deactivate',
  }),
  row(
    1843,
    'settings.auth_updated',
    MASKED,
    { type: 'setting', id: 2 },
    '2026-11-03T18:00:00+00:00',
    {
      oldValues: { entity_second_factor_enabled: true },
      newValues: { entity_second_factor_enabled: false },
      reason: 'تعطيل مؤقت أثناء عرض تجريبي للعميل بموافقة مكتوبة.',
    },
  ),
  row(
    1842,
    'contact_messages.purged',
    SYSTEM,
    { type: 'housekeeping_run', id: 77 },
    '2026-11-03T00:45:00+00:00',
    {
      newValues: { purged: 3 },
    },
  ),
  row(1841, 'audit.exported', ADMIN, { type: 'user', id: 1 }, '2026-11-02T13:10:00+00:00', {
    newValues: { row_count: 120 },
    reason: 'مراجعة ربع سنوية لطلبات الإسناد.',
    tags: ['export'],
  }),
  row(1840, 'user.login', ADMIN, { type: 'user', id: 1 }, '2026-11-02T07:58:00+00:00'),
]
