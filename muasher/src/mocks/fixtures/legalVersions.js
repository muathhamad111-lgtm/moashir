const PUBLISHED_AT = '2026-11-04T11:20:05+00:00'
const EARLIER_AT = '2026-10-01T09:00:00+00:00'

const TERMS_SOURCE = {
  ar: [
    'مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]',
    '',
    '## 1. التعريفات',
    '',
    '- «المنصة»: منصة مؤشر الرقمية.',
    '- «الجهة»: الجمعية أو المؤسسة المسجلة.',
    '',
    '## 2. التزامات الجهة',
    '',
    'تلتزم الجهة بصحة البيانات والشواهد التي ترفعها، وبالمحافظة على سرية بيانات الدخول.',
  ].join('\n'),
  en: [
    'Draft for legal review — last updated: [[approval date]]',
    '',
    '## 1. Definitions',
    '',
    '- "Platform": the Moashir digital platform.',
    '- "Organization": the registered association or foundation.',
    '',
    '## 2. Obligations of the organization',
    '',
    'The organization is responsible for the accuracy of the data and evidence it uploads and for keeping its sign-in details confidential.',
  ].join('\n'),
}

const PRIVACY_SOURCE = {
  ar: [
    'مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]',
    '',
    '## 1. مقدمة ونطاق السياسة',
    '',
    'توضّح هذه السياسة كيف تجمع منصة مؤشر البيانات الشخصية وتستخدمها وتحفظها، وحقوقكم في الاطلاع عليها وتصحيحها.',
    '',
    '## 3. مدد الاحتفاظ',
    '',
    '| فئة البيانات | مدة الاحتفاظ |',
    '|---|---|',
    '| رسائل «تواصل معنا» | 24 شهراً |',
    '| النسخ الاحتياطية المشفرة | 30 يوماً، ثم تُتلف |',
  ].join('\n'),
  en: [
    'Draft for legal review — last updated: [[approval date]]',
    '',
    '## 1. Introduction and scope',
    '',
    'This policy explains how the Moashir platform collects, uses and keeps personal data, and your rights to see and correct it.',
    '',
    '## 3. Retention',
    '',
    '| Data category | Retention period |',
    '|---|---|',
    '| "Contact us" messages | 24 months |',
    '| Encrypted backups | 30 days, then destroyed |',
  ].join('\n'),
}

function version(id, kind, label, status, title, body, publishedAt) {
  return {
    id,
    kind,
    version: label,
    status,
    title,
    body,
    effectiveFrom: publishedAt ? publishedAt.slice(0, 10) : null,
    publishedAt,
    createdAt: publishedAt ?? '2026-11-04T12:00:00+00:00',
    updatedAt: publishedAt ?? '2026-11-04T12:00:00+00:00',
  }
}

const TERMS_TITLE = { ar: 'شروط الاستخدام — منصة مؤشر', en: 'Terms of Use — Moashir' }
const PRIVACY_TITLE = { ar: 'سياسة الخصوصية — منصة مؤشر', en: 'Privacy Policy — Moashir' }

export const legalVersionsFixture = [
  version(15, 'terms', '2026-12', 'draft', TERMS_TITLE, TERMS_SOURCE, null),
  version(13, 'terms', '2026-11-draft', 'current', TERMS_TITLE, TERMS_SOURCE, PUBLISHED_AT),
  version(9, 'terms', 'demo-1', 'superseded', TERMS_TITLE, TERMS_SOURCE, EARLIER_AT),
  version(14, 'privacy', '2026-11-draft', 'current', PRIVACY_TITLE, PRIVACY_SOURCE, PUBLISHED_AT),
  version(10, 'privacy', 'demo-1', 'superseded', PRIVACY_TITLE, PRIVACY_SOURCE, EARLIER_AT),
]
