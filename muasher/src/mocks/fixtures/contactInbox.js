const HANDLERS = [
  { id: 1, name: 'ريم بنت خالد الحربي' },
  { id: 31, name: 'أ. هيفاء بنت سليمان العنزي' },
]

function entry(id, kind, body, author, createdAt) {
  return { id, kind, body, author, createdAt }
}

const SENDER = (name) => ({ kind: 'sender', id: null, name })
const STAFF = (person) => ({ kind: 'staff', id: person.id, name: person.name })

export const CONTACT_INBOX_HANDLERS = HANDLERS

export const contactInboxFixture = [
  {
    id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e01',
    reference: 'CNT-2026-000052',
    status: 'new',
    enquiryType: 'accredited_assessment',
    sender: {
      name: 'نورة بنت فهد العتيبي',
      email: 'info@namaa.example.sa',
      mobile: '+966500005001',
      organization: 'مؤسسة نماء للتنمية',
      locale: 'ar',
    },
    isLinked: true,
    linkedUser: { id: 5001, name: 'نورة بنت فهد العتيبي' },
    entity: {
      id: 5001,
      reference: 'ENT-005001',
      name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
      status: 'active',
    },
    assignee: null,
    createdAt: '2026-11-04T08:10:00+00:00',
    lastActivityAt: '2026-11-04T08:10:00+00:00',
    repliedAt: null,
    closedAt: null,
    closedBy: null,
    entries: [
      entry(
        9001,
        'message',
        'نرجو توضيح الشواهد المطلوبة للمعيار الفرعي الخاص بقياس الأثر، فلدينا تقارير سنوية ولا نعرف إن كانت كافية.',
        SENDER('نورة بنت فهد العتيبي'),
        '2026-11-04T08:10:00+00:00',
      ),
    ],
  },
  {
    id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e02',
    reference: 'CNT-2026-000051',
    status: 'in-progress',
    enquiryType: 'payments',
    sender: {
      name: 'عبدالعزيز بن ناصر الدوسري',
      email: 'finance@albayan.example.sa',
      mobile: null,
      organization: 'جمعية البيان للتنمية المجتمعية',
      locale: 'ar',
    },
    isLinked: false,
    linkedUser: null,
    entity: null,
    assignee: HANDLERS[1],
    createdAt: '2026-11-03T11:30:00+00:00',
    lastActivityAt: '2026-11-03T13:05:00+00:00',
    repliedAt: null,
    closedAt: null,
    closedBy: null,
    entries: [
      entry(
        9002,
        'message',
        'دفعنا رسوم التقييم المعتمد ولم يصلنا إيصال الدفع على البريد بعد.',
        SENDER('عبدالعزيز بن ناصر الدوسري'),
        '2026-11-03T11:30:00+00:00',
      ),
      entry(
        9003,
        'note',
        'راجعت عمليات الدفع: لا توجد عملية مكتملة بهذا البريد، أتحقق من الفريق المالي.',
        STAFF(HANDLERS[1]),
        '2026-11-03T13:05:00+00:00',
      ),
    ],
  },
  {
    id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e03',
    reference: 'CNT-2026-000049',
    status: 'replied',
    enquiryType: 'technical_support',
    sender: {
      name: 'Laila Al-Harbi',
      email: 'laila@alwafa.example.sa',
      mobile: '+966500006003',
      organization: 'Al-Wafa Family Development Association',
      locale: 'en',
    },
    isLinked: false,
    linkedUser: null,
    entity: null,
    assignee: HANDLERS[0],
    createdAt: '2026-11-01T07:00:00+00:00',
    lastActivityAt: '2026-11-01T09:20:00+00:00',
    repliedAt: '2026-11-01T09:20:00+00:00',
    closedAt: null,
    closedBy: null,
    entries: [
      entry(
        9004,
        'message',
        'The sign-in code does not reach our official e-mail address.',
        SENDER('Laila Al-Harbi'),
        '2026-11-01T07:00:00+00:00',
      ),
      entry(
        9005,
        'reply',
        'Please check the junk folder and allow messages from the platform domain; the code is valid for 5 minutes.',
        STAFF(HANDLERS[0]),
        '2026-11-01T09:20:00+00:00',
      ),
    ],
  },
  {
    id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e04',
    reference: 'CNT-2026-000044',
    status: 'closed',
    enquiryType: 'general',
    sender: {
      name: 'سارة بنت محمد القرني',
      email: 'sara@example.sa',
      mobile: null,
      organization: null,
      locale: 'ar',
    },
    isLinked: false,
    linkedUser: null,
    entity: null,
    assignee: HANDLERS[0],
    createdAt: '2026-10-20T10:00:00+00:00',
    lastActivityAt: '2026-10-21T08:00:00+00:00',
    repliedAt: '2026-10-20T14:00:00+00:00',
    closedAt: '2026-10-21T08:00:00+00:00',
    closedBy: HANDLERS[0],
    entries: [
      entry(
        9006,
        'message',
        'هل تتاح المنصة للجهات الحكومية أم للجمعيات الأهلية فقط؟',
        SENDER('سارة بنت محمد القرني'),
        '2026-10-20T10:00:00+00:00',
      ),
      entry(
        9007,
        'reply',
        'تتاح المنصة للجهات المسجلة وفق شروط التسجيل المنشورة في صفحة التسجيل.',
        STAFF(HANDLERS[0]),
        '2026-10-20T14:00:00+00:00',
      ),
    ],
  },
]
