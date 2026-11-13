const PUBLISHED_AT = '2026-11-04T11:20:05+00:00'

function heading(text) {
  return { type: 'heading', level: 2, text }
}

function paragraph(text) {
  return { type: 'paragraph', text }
}

function list(items, ordered = false) {
  return { type: 'list', ordered, start: ordered ? 1 : null, items }
}

export const legalTextsFixture = {
  privacy: {
    id: 14,
    kind: 'privacy',
    version: '2026-11-draft',
    effectiveFrom: '2026-11-04',
    publishedAt: PUBLISHED_AT,
    title: { ar: 'سياسة الخصوصية — منصة مؤشر', en: 'Privacy Policy — Moashir' },
    body: {
      ar: [
        paragraph('مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]'),
        heading('1. مقدمة ونطاق السياسة'),
        paragraph(
          'توضّح هذه السياسة كيف تجمع منصة مؤشر البيانات الشخصية وتستخدمها وتحفظها، وحقوقكم في الاطلاع عليها وتصحيحها.',
        ),
        heading('2. الفئات المشمولة'),
        list([
          'زوار المنصة؛',
          'الجهات المسجلة ومسؤولو حساباتها؛',
          'المرسلون عبر نموذج «تواصل معنا».',
        ]),
        heading('3. مدد الاحتفاظ'),
        {
          type: 'table',
          header: ['فئة البيانات', 'مدة الاحتفاظ'],
          rows: [
            ['رسائل «تواصل معنا»', '24 شهراً'],
            ['النسخ الاحتياطية المشفرة', '30 يوماً، ثم تُتلف'],
          ],
        },
        heading('4. حقوقكم'),
        list(
          [
            'الحق في العلم بكيفية معالجة بياناتكم.',
            'الحق في الوصول إلى بياناتكم وطلب نسخة منها.',
            'الحق في تصحيح بياناتكم أو إتلافها وفق النظام.',
          ],
          true,
        ),
        paragraph('لتقديم طلب يتعلق بالبيانات الشخصية اختاروا هذا النوع في نموذج «تواصل معنا».'),
      ],
      en: [
        paragraph('Draft for legal review — last updated: [[approval date]]'),
        heading('1. Introduction and scope'),
        paragraph(
          'This policy explains how the Moashir platform collects, uses and keeps personal data, and your rights to see and correct it.',
        ),
        heading('2. People covered'),
        list([
          'visitors of the platform;',
          'registered organizations and their account owners;',
          'senders of the Contact us form.',
        ]),
        heading('3. Retention'),
        {
          type: 'table',
          header: ['Data category', 'Retention period'],
          rows: [
            ['"Contact us" messages', '24 months'],
            ['Encrypted backups', '30 days, then destroyed'],
          ],
        },
        heading('4. Your rights'),
        list(
          [
            'The right to be informed of how your data is processed.',
            'The right to access your data and request a copy.',
            'The right to correct or destroy your data as the law provides.',
          ],
          true,
        ),
        paragraph('To submit a personal data request, choose that type in the Contact us form.'),
      ],
    },
  },
  terms: {
    id: 13,
    kind: 'terms',
    version: '2026-11-draft',
    effectiveFrom: '2026-11-04',
    publishedAt: PUBLISHED_AT,
    title: { ar: 'شروط الاستخدام — منصة مؤشر', en: 'Terms of Use — Moashir' },
    body: {
      ar: [
        paragraph('مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]'),
        heading('1. التعريفات'),
        list(['«المنصة»: منصة مؤشر الرقمية.', '«الجهة»: الجمعية أو المؤسسة المسجلة.']),
        heading('2. التزامات الجهة'),
        paragraph(
          'تلتزم الجهة بصحة البيانات والشواهد التي ترفعها، وبالمحافظة على سرية بيانات الدخول.',
        ),
      ],
      en: [
        paragraph('Draft for legal review — last updated: [[approval date]]'),
        heading('1. Definitions'),
        list([
          '"Platform": the Moashir digital platform.',
          '"Organization": the registered association or foundation.',
        ]),
        heading('2. Obligations of the organization'),
        paragraph(
          'The organization is responsible for the accuracy of the data and evidence it uploads and for keeping its sign-in details confidential.',
        ),
      ],
    },
  },
}

export const accessibilityPageFixture = {
  key: 'accessibility',
  title: { ar: 'بيان إمكانية الوصول — منصة مؤشر', en: 'Accessibility Statement — Moashir' },
  seo: { title: null, description: null },
  updatedAt: '2026-11-04T11:20:04+00:00',
  publishedAt: PUBLISHED_AT,
  sections: [
    {
      id: 91,
      key: 'intro',
      type: 'rich_text',
      title: null,
      blocks: {
        ar: [paragraph('مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]')],
        en: [paragraph('Draft for legal review — last updated: [[approval date]]')],
      },
    },
    {
      id: 93,
      key: 'section-2',
      type: 'rich_text',
      title: { ar: '2. المعيار المعتمد', en: '2. Standard adopted' },
      blocks: {
        ar: [
          list([
            'نعتمد إرشادات إمكانية الوصول إلى محتوى الويب WCAG 2.1 بالمستوى AA.',
            'نختبر الواجهة باللغتين العربية والإنجليزية وبالوضعين الفاتح والداكن.',
          ]),
        ],
        en: [
          list([
            'We adopt the Web Content Accessibility Guidelines (WCAG) 2.1 at level AA.',
            'We test the interface in Arabic and English and in light and dark modes.',
          ]),
        ],
      },
    },
  ],
}
