export const assessmentFixture = {
  scale: 'of-10',
  advancedMinEntityAge: 3,
  advancedDisqualificationScore: 60,
}

export const kbFilesFixture = [
  {
    id: 'kb-1',
    name: 'دليل استخدام المنصة.pdf',
    sizeKb: 1840,
    status: 'processed',
    approved: true,
    uploadedAt: '2026-06-28T10:00:00Z',
  },
  {
    id: 'kb-2',
    name: 'شرح معايير التقييم.pdf',
    sizeKb: 3120,
    status: 'processed',
    approved: true,
    uploadedAt: '2026-06-29T14:30:00Z',
  },
  {
    id: 'kb-3',
    name: 'دليل نموذج التميز.pdf',
    sizeKb: 2540,
    status: 'processed',
    approved: true,
    uploadedAt: '2026-06-30T09:15:00Z',
  },
  {
    id: 'kb-4',
    name: 'الأسئلة الشائعة.docx',
    sizeKb: 420,
    status: 'processing',
    approved: false,
    uploadedAt: '2026-07-06T08:40:00Z',
  },
  {
    id: 'kb-5',
    name: 'سياسة الخصوصية.pdf',
    sizeKb: 680,
    status: 'failed',
    approved: false,
    uploadedAt: '2026-07-06T09:05:00Z',
  },
]
