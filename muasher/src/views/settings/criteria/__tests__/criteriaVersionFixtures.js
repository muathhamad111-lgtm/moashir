// Versions and edit events as the criteria API returns them (camelCase after the client).

export const VERSION_1 = {
  id: 1,
  versionNumber: 1,
  status: 'archived',
  label: null,
  publishedAt: '2026-10-31T09:00:00Z',
}

export const VERSION_2 = {
  id: 3,
  versionNumber: 2,
  status: 'published',
  label: { ar: 'مراجعة 2027', en: '2027 review' },
  publishedAt: '2026-11-18T09:30:00Z',
}

export const DRAFT_3 = { id: 5, versionNumber: 3, status: 'draft', label: null, publishedAt: null }

export const PUBLICATION_2 = {
  id: 2,
  kind: 'structural',
  scope: 'all',
  fromVersion: { id: 1, versionNumber: 1, label: null },
  toVersion: { id: 3, versionNumber: 2, label: { ar: 'مراجعة 2027', en: '2027 review' } },
  changeCount: 3,
  scoreAffectingCount: 2,
  changes: [
    {
      nodeType: 'sub',
      code: 'M1.1',
      change: 'modified',
      fields: ['weight'],
      before: { weight: '400.00' },
      after: { weight: '500.00' },
      scoreAffecting: true,
      label: { ar: 'التخطيط', en: 'Planning' },
    },
    {
      nodeType: 'sub',
      code: 'M1.2',
      change: 'modified',
      fields: ['name'],
      before: { name: { ar: 'الحوكمة', en: 'Governance' } },
      after: { name: { ar: 'تميز الحوكمة', en: 'Governance excellence' } },
      scoreAffecting: false,
      label: { ar: 'تميز الحوكمة', en: 'Governance excellence' },
    },
    {
      nodeType: 'sub',
      code: 'M2.4',
      change: 'added',
      fields: [],
      before: {},
      after: {},
      scoreAffecting: true,
      label: { ar: 'إدارة المخاطر', en: 'Risk management' },
    },
  ],
  affectedOpenCount: 4,
  reason: 'Annual review of the model',
  editedBy: { id: 7, name: 'Khalid Al-Omari' },
  createdAt: '2026-11-18T09:30:00Z',
}

export const CORRECTION_2 = {
  id: 4,
  kind: 'text_correction',
  scope: 'all',
  fromVersion: { id: 3, versionNumber: 2, label: null },
  toVersion: null,
  changeCount: 1,
  scoreAffectingCount: 0,
  changes: [],
  affectedOpenCount: 0,
  reason: null,
  editedBy: null,
  createdAt: '2026-11-19T10:00:00Z',
}
