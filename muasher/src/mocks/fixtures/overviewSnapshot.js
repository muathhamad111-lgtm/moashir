// The platform snapshot of the executive overview (as it stands now, whatever the period).
export const LOWEST_CRITERIA = [
  {
    code: '4',
    name: { ar: 'إدارة الموارد', en: 'Resource management' },
    averagePercent: '58.40',
    results: 6,
  },
  {
    code: '7',
    name: { ar: 'قياس الأثر', en: 'Impact measurement' },
    averagePercent: '61.25',
    results: 6,
  },
  {
    code: '2',
    name: { ar: 'التخطيط الاستراتيجي', en: 'Strategic planning' },
    averagePercent: '66.10',
    results: 6,
  },
]

export const OVERVIEW_SNAPSHOT = [
  {
    key: 'entities_by_governance',
    data: [
      { key: 'below_50', count: 3 },
      { key: 'from_50_to_69', count: 9 },
      { key: 'from_70_to_84', count: 17 },
      { key: 'from_85_to_100', count: 8 },
      { key: 'not_reported', count: 5 },
    ],
  },
  {
    key: 'entities_by_sector',
    data: [
      { key: 'social', count: 16 },
      { key: 'educational', count: 11 },
      { key: 'health', count: 8 },
      { key: 'developmental', count: 7 },
    ],
  },
  {
    key: 'entities_by_founding_decade',
    data: [
      { key: '1980', count: 2 },
      { key: '1990', count: 5 },
      { key: '2000', count: 9 },
      { key: '2010', count: 15 },
      { key: '2020', count: 11 },
    ],
  },
  { key: 'lowest_criteria', data: LOWEST_CRITERIA },
]
