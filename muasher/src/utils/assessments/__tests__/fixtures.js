export const criteriaTree = {
  versionId: 3,
  versionNumber: 2,
  publishedAt: '2026-10-31T09:00:00Z',
  scaleKind: 'of-10',
  mains: [
    {
      id: 1,
      code: 'M1',
      name: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' },
      weight: '1000.00',
      subs: [
        {
          id: 101,
          code: 'M1.1',
          name: { ar: 'التوجه الاستراتيجي والرؤية', en: 'Strategic Direction and Vision' },
          weight: '400.00',
          evaluationType: 'guiding-points',
          points: [
            { id: 1001, code: 'M1.1.1', description: { ar: 'وجود وثيقة رؤية معتمدة', en: 'An approved vision document' }, weight: '200.00' },
            { id: 1002, code: 'M1.1.2', description: { ar: 'أهداف مرتبطة بالرؤية', en: 'Goals linked to the vision' }, weight: '200.00' },
          ],
        },
        {
          id: 102,
          code: 'M1.2',
          name: { ar: 'الحوكمة والامتثال', en: 'Governance and Compliance' },
          weight: '600.00',
          evaluationType: 'direct',
          points: [],
        },
      ],
    },
  ],
}

export function formWith({ scores = { subs: {}, points: {} }, versionId = 3, createdAt = '2026-11-01T06:00:00Z', criteria = criteriaTree } = {}) {
  return {
    assessment: { id: 9002, number: 'SLF-2026-000002', criteriaVersionId: versionId, createdAt, status: 'draft', isFrozen: false },
    scale: { kind: 'of-10', maximum: '10.00' },
    scores,
    progress: { scored: 0, required: 3 },
    criteria: { ...criteria, versionId },
  }
}
