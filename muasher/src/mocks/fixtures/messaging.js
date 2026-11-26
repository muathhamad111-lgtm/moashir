export const segmentsFixture = [
  {
    key: 'self-only-not-accredited',
    label: {
      ar: 'أجرَوا التقييم الذاتي فقط دون المعتمد',
      en: 'Did self assessment only, not accredited',
    },
    count: 46,
  },
  {
    key: 'registered-advanced-not-qualified',
    label: { ar: 'سجّلوا في المتقدم ولم يتأهلوا', en: 'Registered for advanced but not qualified' },
    count: 18,
  },
  { key: 'qualified', label: { ar: 'الجهات المتأهلة', en: 'Qualified entities' }, count: 12 },
  {
    key: 'last-was-accredited',
    label: { ar: 'آخر تقييم لها كان معتمدًا', en: 'Last assessment was accredited' },
    count: 27,
  },
  { key: 'all', label: { ar: 'جميع الجهات المسجّلة', en: 'All registered entities' }, count: 118 },
  {
    key: 'individual',
    label: { ar: 'اختيار جهة محددة', en: 'Pick an individual entity' },
    count: 0,
  },
]

export const recipientsFixture = [
  {
    id: 5001,
    name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    email: 'info@albirr-riyadh.example.sa',
  },
  {
    id: 5002,
    name: { ar: 'مؤسسة إنسان الأهلية', en: 'Insan Foundation' },
    email: 'contact@insan.example.sa',
  },
  {
    id: 5003,
    name: { ar: 'وقف الرحمة الخيري', en: 'Al-Rahma Waqf' },
    email: 'waqf@alrahma.example.sa',
  },
  {
    id: 5004,
    name: { ar: 'جمعية تنمية الأسرة', en: 'Family Development Association' },
    email: 'info@fda.example.sa',
  },
  {
    id: 5005,
    name: { ar: 'جمعية تحفيظ القرآن بجدة', en: 'Quran Memorization Society — Jeddah' },
    email: 'jeddah@tahfeez.example.sa',
  },
  {
    id: 5006,
    name: { ar: 'مؤسسة عطاء للتنمية', en: 'Ataa Development Foundation' },
    email: 'hello@ataa.example.sa',
  },
  {
    id: 5007,
    name: { ar: 'جمعية رعاية الأيتام بالدمام', en: 'Orphan Care Society — Dammam' },
    email: 'dammam@ory.example.sa',
  },
  {
    id: 5008,
    name: { ar: 'جمعية أصدقاء المرضى', en: 'Patients Friends Society' },
    email: 'care@pfs.example.sa',
  },
]
