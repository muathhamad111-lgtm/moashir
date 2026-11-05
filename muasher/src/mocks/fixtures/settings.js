export const generalFixture = {
  platformName: {
    ar: 'منصة مؤشر للتميز المؤسسي',
    en: 'Moashir Institutional Excellence Platform',
  },
  tagline: {
    ar: 'قياس وتطوير التميز في القطاع غير الربحي',
    en: 'Measuring and developing excellence in the non-profit sector',
  },
  logo: { name: 'muasher-logo.svg', url: '/assets/brand/muasher-logo.svg' },
  primaryColor: '#3DE9AD',
  secondaryColor: '#D9F7F7',
  content: {
    ar: 'منصة مؤشر تمكّن الجمعيات والمؤسسات الأهلية من قياس أدائها وفق نموذج التميز، وتقديم تقييمات ذاتية ومعتمدة ومتقدمة.',
    en: 'Moashir enables associations and non-profit foundations to measure their performance against the excellence model through self, accredited, and advanced assessments.',
  },
  contactEmail: 'support@moashir.example.sa',
  contactPhone: '+966112004000',
  contactAddress: {
    ar: 'الرياض، حي العليا، طريق الملك فهد',
    en: 'Riyadh, Al Olaya District, King Fahd Road',
  },
}

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
