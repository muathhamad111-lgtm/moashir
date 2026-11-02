export const KPI_CATEGORIES = [
  { key: 'institutional-excellence', label: { ar: 'التميز المؤسسي', en: 'Institutional excellence' } },
  { key: 'performance', label: { ar: 'الأداء', en: 'Performance' } },
  { key: 'beneficiaries', label: { ar: 'المستفيدون', en: 'Beneficiaries' } },
  { key: 'human-resources', label: { ar: 'الموارد البشرية', en: 'Human resources' } },
  { key: 'innovation', label: { ar: 'الابتكار', en: 'Innovation' } },
  { key: 'operations', label: { ar: 'العمليات', en: 'Operations' } },
  { key: 'knowledge', label: { ar: 'المعرفة', en: 'Knowledge' } },
  { key: 'key-results', label: { ar: 'النتائج الرئيسية', en: 'Key results' } },
]

export const KPI_CATEGORY_KEYS = KPI_CATEGORIES.map((category) => category.key)

export const KPI_STATUSES = [
  { key: 'excellent', label: { ar: 'متميز', en: 'Excellent' }, variant: 'success' },
  { key: 'good', label: { ar: 'جيد', en: 'Good' }, variant: 'info' },
  { key: 'needs-improvement', label: { ar: 'يحتاج إلى تحسين', en: 'Needs improvement' }, variant: 'warning' },
  { key: 'behind-target', label: { ar: 'دون المستهدف', en: 'Behind target' }, variant: 'danger' },
]

export const KPI_STATUS_KEYS = KPI_STATUSES.map((status) => status.key)

export const KPI_STATUS_VARIANTS = KPI_STATUSES.reduce((map, status) => {
  map[status.key] = status.variant
  return map
}, {})
