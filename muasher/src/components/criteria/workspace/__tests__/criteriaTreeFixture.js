export function workspaceTree() {
  return {
    version: { id: 4, versionNumber: 2, status: 'draft' },
    activeWeightTotal: '1000.00',
    mains: [
      {
        id: 11,
        code: 'M1',
        name: { ar: 'القيادة', en: 'Leadership' },
        weight: '1000.00',
        remainingWeight: '0.00',
        allocatedWeight: '1000.00',
        distributeEqually: false,
        weightBalanced: true,
        active: true,
        subs: [
          {
            id: 21,
            code: 'M1.1',
            name: { ar: 'التخطيط', en: 'Planning' },
            weight: '600.00',
            evaluationType: 'guiding-points',
            allocatedPointWeight: '500.00',
            remainingPointWeight: '100.00',
            distributeEqually: false,
            active: true,
            attachments: [{ key: 'plan', name: { ar: 'الخطة', en: 'Plan' }, required: true }],
            points: [
              {
                id: 31,
                code: 'M1.1.1',
                description: { ar: 'خطة معتمدة', en: 'Approved plan' },
                weight: '500.00',
                active: true,
                attachments: [],
              },
            ],
          },
          {
            id: 22,
            code: 'M1.2',
            name: { ar: 'الحوكمة', en: 'Governance' },
            weight: '400.00',
            evaluationType: 'direct',
            active: true,
            attachments: [],
            points: [],
          },
        ],
      },
      {
        id: 12,
        code: 'M2',
        name: { ar: 'النتائج', en: 'Results' },
        weight: '500.00',
        remainingWeight: '500.00',
        allocatedWeight: '0.00',
        weightBalanced: false,
        active: false,
        subs: [],
      },
    ],
  }
}
