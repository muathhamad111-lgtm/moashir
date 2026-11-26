// The staff non-disclosure agreement in the mocks: one current version every fixture account has accepted, so
// the modal stays out of unrelated flows. The `nda-pending` scenario makes the signed-in account owe it.
export const NDA_PUBLISHED_AT = '2026-11-22T09:00:00+00:00'

export const NDA_DEFAULT_ROLES = Object.freeze([
  'system_admin',
  'criteria_officer',
  'request_follower',
  'assessor',
  'filtering_officer',
  'award_secretary',
])

const BODY = {
  ar: [
    '## 1. الغرض',
    'تحدد هذه الاتفاقية التزامات منسوبي منصة مؤشر بالمحافظة على سرية المعلومات التي يطّلعون عليها بحكم مهامهم.',
    '## 2. المعلومات السرية',
    'بيانات الجهات، وملفات الشواهد، ونتائج التقييم، ومداولات اللجان، وأي معلومة غير منشورة.',
    '## 3. الالتزامات',
    'عدم الإفصاح عن المعلومات السرية أو نسخها أو مشاركتها خارج نطاق المهمة، وعدم استخدامها لأي غرض آخر.',
    '## 4. تعارض المصالح',
    'الإبلاغ فورًا عن أي علاقة بجهة مُقيَّمة، والاعتذار عن تقييمها.',
    '## 5. المدة',
    'يسري الالتزام طوال مدة العمل وبعد انتهائها دون حد زمني.',
    '## 6. المخالفة',
    'تُحال أي مخالفة إلى الجهة المختصة وفق الأنظمة المعمول بها.',
  ].join('\n\n'),
  en: [
    '## 1. Purpose',
    'This agreement sets out the duty of Moashir staff to keep confidential the information they see in their work.',
    '## 2. Confidential information',
    'Entity data, evidence files, assessment results, committee deliberations and any unpublished information.',
    '## 3. Obligations',
    'Not to disclose, copy or share confidential information outside the task, nor use it for any other purpose.',
    '## 4. Conflict of interest',
    'Report at once any relationship with an assessed entity, and step aside from assessing it.',
    '## 5. Duration',
    'The duty applies throughout the work and after it ends, without a time limit.',
    '## 6. Breach',
    'Any breach is referred to the competent authority under the applicable regulations.',
  ].join('\n\n'),
}

export const ndaVersionsFixture = [
  {
    id: 501,
    kind: 'nda',
    version: '1.0',
    status: 'current',
    title: {
      ar: 'اتفاقية عدم الإفصاح والسرية',
      en: 'Non-disclosure and confidentiality agreement',
    },
    body: BODY,
    changeSummary: { ar: 'الإصدار الأول من الاتفاقية.', en: 'The first version of the agreement.' },
    publishedBy: 'منيرة بنت خالد العتيبي',
    effectiveFrom: NDA_PUBLISHED_AT.slice(0, 10),
    publishedAt: NDA_PUBLISHED_AT,
    createdAt: NDA_PUBLISHED_AT,
    updatedAt: NDA_PUBLISHED_AT,
  },
]
