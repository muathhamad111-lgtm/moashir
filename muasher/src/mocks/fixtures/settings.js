export const generalFixture = {
  platformName: {
    ar: 'منصة مؤشر للتميز المؤسسي',
    en: 'Muasher Institutional Excellence Platform',
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
    en: 'Muasher enables associations and non-profit foundations to measure their performance against the excellence model through self, accredited, and advanced assessments.',
  },
  contactEmail: 'support@moashir.example.sa',
  contactPhone: '+966112004000',
  contactAddress: {
    ar: 'الرياض، حي العليا، طريق الملك فهد',
    en: 'Riyadh, Al Olaya District, King Fahd Road',
  },
}

export const otpFixture = {
  emailOtp: true,
  smsOtp: true,
  emailPassword: true,
}

export const assessmentFixture = {
  scale: 'of-10',
  advancedMinEntityAge: 3,
  advancedDisqualificationScore: 60,
}

export const certificatesFixture = [
  {
    kind: 'accredited',
    templateFile: { name: 'accredited-template.pdf', url: '/assets/certificates/accredited.pdf' },
    entityNamePosition: { x: 50, y: 42 },
    scorePosition: { x: 50, y: 58 },
    fontColor: '#022426',
    fontType: 'iwanzaza',
    fontSize: 28,
    issuanceEnabled: true,
  },
  {
    kind: 'advanced-winner',
    templateFile: { name: 'advanced-winner-template.pdf', url: '/assets/certificates/advanced-winner.pdf' },
    entityNamePosition: { x: 50, y: 40 },
    scorePosition: { x: 50, y: 60 },
    fontColor: '#1FB57A',
    fontType: 'iwanzaza',
    fontSize: 32,
    issuanceEnabled: true,
  },
  {
    kind: 'advanced-qualified',
    templateFile: null,
    entityNamePosition: { x: 50, y: 45 },
    scorePosition: { x: 50, y: 62 },
    fontColor: '#334444',
    fontType: 'montserrat',
    fontSize: 24,
    issuanceEnabled: false,
  },
]

export const CERTIFICATE_FONTS = ['iwanzaza', 'montserrat', 'tajawal', 'cairo']

export const kbFilesFixture = [
  { id: 'kb-1', name: 'دليل استخدام المنصة.pdf', sizeKb: 1840, status: 'processed', approved: true, uploadedAt: '2026-06-28T10:00:00Z' },
  { id: 'kb-2', name: 'شرح معايير التقييم.pdf', sizeKb: 3120, status: 'processed', approved: true, uploadedAt: '2026-06-29T14:30:00Z' },
  { id: 'kb-3', name: 'دليل نموذج التميز.pdf', sizeKb: 2540, status: 'processed', approved: true, uploadedAt: '2026-06-30T09:15:00Z' },
  { id: 'kb-4', name: 'الأسئلة الشائعة.docx', sizeKb: 420, status: 'processing', approved: false, uploadedAt: '2026-07-06T08:40:00Z' },
  { id: 'kb-5', name: 'سياسة الخصوصية.pdf', sizeKb: 680, status: 'failed', approved: false, uploadedAt: '2026-07-06T09:05:00Z' },
]

export const notificationTypesFixture = [
  {
    code: 'REG-01',
    category: 'registration',
    label: { ar: 'تسجيل جهة جديدة', en: 'New entity registered' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تم إنشاء حسابكم في منصة مؤشر بنجاح. يمكنكم الآن الدخول واستكمال بيانات الجهة.',
      en: 'Your Muasher account was created successfully. You can now sign in and complete your entity data.',
    },
  },
  {
    code: 'REG-02',
    category: 'registration',
    label: { ar: 'تأكيد البريد أو الجوال', en: 'Verify email / phone' },
    emailEnabled: true,
    smsEnabled: true,
    template: {
      ar: 'رمز التحقق الخاص بمنصة مؤشر هو: {OTP}.',
      en: 'Your Muasher verification code is: {OTP}.',
    },
  },
  {
    code: 'REG-05',
    category: 'registration',
    label: { ar: 'تعطيل حساب جهة', en: 'Deactivate entity account' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تم تعطيل حساب الجهة مؤقتًا. يرجى التواصل مع إدارة المنصة عند الحاجة.',
      en: 'The entity account has been temporarily deactivated. Please contact the platform administration if needed.',
    },
  },
  {
    code: 'PAY-02',
    category: 'payment',
    label: { ar: 'نجاح الدفع', en: 'Payment success' },
    emailEnabled: true,
    smsEnabled: true,
    template: {
      ar: 'تم استلام رسوم التقييم المعتمد بنجاح، وتم فتح نموذج التقييم لكم.',
      en: 'The accredited assessment fees were received successfully, and the assessment form is now open for you.',
    },
  },
  {
    code: 'PAY-03',
    category: 'payment',
    label: { ar: 'فشل الدفع', en: 'Payment failed' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تعذّر إتمام عملية الدفع للتقييم المعتمد. يرجى إعادة المحاولة أو مراجعة بوابة الدفع.',
      en: 'The accredited assessment payment could not be completed. Please retry or check the payment gateway.',
    },
  },
  {
    code: 'ACC-08',
    category: 'accredited',
    label: { ar: 'إكمال المقيّم للتقييم', en: 'Assessor completed the assessment' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تم الانتهاء من تقييم طلبكم المعتمد، والنتيجة متاحة الآن.',
      en: 'Your accredited assessment has been completed, and the result is now available.',
    },
  },
  {
    code: 'ACC-09',
    category: 'accredited',
    label: { ar: 'إصدار شهادة التقييم المعتمد', en: 'Accredited certificate issued' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تم إصدار شهادة التقييم المعتمد للجهة {اسم الجهة}.',
      en: 'The accredited assessment certificate was issued for {entity name}.',
    },
  },
  {
    code: 'ADV-02',
    category: 'advanced',
    label: { ar: 'قرب انتهاء فترة التقديم', en: 'Submission deadline approaching' },
    emailEnabled: true,
    smsEnabled: true,
    template: {
      ar: 'تبقّى {عدد الأيام} أيام على انتهاء فترة التقديم للتقييم المتقدم.',
      en: '{days} days remain before the advanced assessment submission period ends.',
    },
  },
  {
    code: 'ADV-17',
    category: 'advanced',
    label: { ar: 'اعتماد الأمين للنتيجة', en: 'Secretary approved the result' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تم اعتماد نتيجة التقييم المتقدم للجهة، وستُعرض وفق سياسة الإعلان.',
      en: 'The advanced assessment result has been approved and will be shown per the announcement policy.',
    },
  },
  {
    code: 'INT-01',
    category: 'integrations',
    label: { ar: 'فشل إرسال رسالة نصية', en: 'SMS send failed' },
    emailEnabled: true,
    smsEnabled: false,
    template: {
      ar: 'تعذّر إرسال رسالة نصية عبر المزود. يرجى مراجعة إعدادات بوابة الرسائل.',
      en: 'An SMS could not be sent through the provider. Please review the SMS gateway settings.',
    },
  },
]

export const notificationAuditFixture = [
  { id: 'aud-1', code: 'PAY-02', recipient: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' }, channel: 'email', receivedAt: '2026-07-05T09:15:00Z', openedAt: '2026-07-05T09:22:00Z', actionedAt: '2026-07-05T09:40:00Z' },
  { id: 'aud-2', code: 'ACC-08', recipient: { ar: 'مؤسسة إنسان الأهلية', en: 'Insan Foundation' }, channel: 'in-app', receivedAt: '2026-07-05T12:00:00Z', openedAt: '2026-07-05T12:05:00Z', actionedAt: null },
  { id: 'aud-3', code: 'REG-02', recipient: { ar: 'وقف الرحمة الخيري', en: 'Al-Rahma Waqf' }, channel: 'sms', receivedAt: '2026-07-06T08:10:00Z', openedAt: null, actionedAt: null },
  { id: 'aud-4', code: 'ADV-02', recipient: { ar: 'جمعية تنمية الأسرة', en: 'Family Development Association' }, channel: 'email', receivedAt: '2026-07-04T16:30:00Z', openedAt: '2026-07-04T17:00:00Z', actionedAt: '2026-07-04T17:20:00Z' },
  { id: 'aud-5', code: 'INT-01', recipient: { ar: 'مسؤول التكامل', en: 'Integrations Officer' }, channel: 'in-app', receivedAt: '2026-07-06T09:00:00Z', openedAt: '2026-07-06T09:03:00Z', actionedAt: null },
]
