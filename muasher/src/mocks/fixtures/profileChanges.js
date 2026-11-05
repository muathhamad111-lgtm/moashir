export const PROFILE_FIELD_LABELS = Object.freeze({
  name: { ar: 'اسم الجهة', en: 'Entity name' },
  entity_type: { ar: 'نوع الجهة', en: 'Entity type' },
  development_sector: { ar: 'القطاع التنموي', en: 'Development sector' },
  org_size: { ar: 'حجم الجهة', en: 'Organization size' },
  founding_year: { ar: 'سنة التأسيس', en: 'Founding year' },
  region_id: { ar: 'المنطقة', en: 'Region' },
  city_id: { ar: 'المدينة', en: 'City' },
  governance_grade: { ar: 'درجة الحوكمة', en: 'Governance grade' },
  employees_count: { ar: 'عدد الموظفين', en: 'Number of employees' },
  annual_beneficiaries_count: { ar: 'عدد المستفيدين سنويًا', en: 'Annual beneficiaries' },
  has_strategic_plan: { ar: 'الخطة الاستراتيجية', en: 'Strategic plan' },
})

const SYSTEM_ADMIN_ROLE = {
  key: 'system_admin',
  label: { ar: 'مدير النظام', en: 'System Administrator' },
}
const FOLLOWER_ROLE = {
  key: 'request_follower',
  label: { ar: 'متابع طلبات التقييم', en: 'Assessment Requests Follower' },
}

export const profileChangesFixture = [
  {
    id: 9001,
    entityId: 5001,
    field: 'governance_grade',
    oldValue: '82.00',
    newValue: '86.50',
    changedBy: 'entity',
    staff: null,
    changedAt: '2026-10-20T07:15:00Z',
  },
  {
    id: 9002,
    entityId: 5001,
    field: 'employees_count',
    oldValue: 165,
    newValue: 180,
    changedBy: 'staff',
    staff: { name: 'منيرة بنت خالد العتيبي', roles: [SYSTEM_ADMIN_ROLE] },
    changedAt: '2026-10-24T10:40:00Z',
  },
  {
    id: 9003,
    entityId: 5001,
    field: 'has_strategic_plan',
    oldValue: false,
    newValue: true,
    changedBy: 'staff',
    staff: { name: null, roles: [] },
    changedAt: '2026-10-26T12:05:00Z',
  },
  {
    id: 9004,
    entityId: 5001,
    field: 'org_size',
    oldValue: 'medium',
    newValue: 'large',
    changedBy: 'system',
    staff: null,
    changedAt: '2026-10-28T05:00:00Z',
  },
  {
    id: 9005,
    entityId: 6001,
    field: 'name',
    oldValue: { ar: 'مؤسسة نماء', en: 'Namaa Foundation' },
    newValue: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    changedBy: 'staff',
    staff: { name: 'ماجد بن سعود الشمري', roles: [FOLLOWER_ROLE] },
    changedAt: '2026-10-22T09:30:00Z',
  },
]
