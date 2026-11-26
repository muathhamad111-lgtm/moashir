// Mock breakdowns of the executive overview (see the API contract). Every figure follows the
// period (through `figures`, the period's own totals) and the board filters (through `scale`).
const GIGABYTE = 1024 ** 3
const STORAGE_QUOTA_GB = 50
const STORAGE_USED_GB = 18.6

function part(total, shares) {
  const keys = Object.keys(shares)
  const weight = keys.reduce((sum, key) => sum + shares[key], 0)
  const rows = keys.map((key) => ({ key, count: Math.floor((total * shares[key]) / weight) }))
  const placed = rows.reduce((sum, row) => sum + row.count, 0)
  if (rows.length) rows[0].count += total - placed
  return rows
}

const REGION_NAMES = Object.freeze({
  riyadh: { ar: 'منطقة الرياض', en: 'Riyadh Region' },
  makkah: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' },
  eastern: { ar: 'المنطقة الشرقية', en: 'Eastern Region' },
  madinah: { ar: 'منطقة المدينة المنورة', en: 'Madinah Region' },
  qassim: { ar: 'منطقة القصيم', en: 'Qassim Region' },
  asir: { ar: 'منطقة عسير', en: 'Asir Region' },
})

const scaled = (value, scale) => Math.round(value * scale)

export function breakdownsFor(
  { requests, completed, newEntities, collected },
  scale,
  showsStorage,
) {
  const entities = scaled(42, scale)
  return {
    requestsByStatus: part(requests, {
      awaiting_payment: 2,
      in_queue: 3,
      under_review: 5,
      needs_completion: 2,
      completed: 6,
      declined: 1,
    }),
    paymentsByStatus: part(Math.max(requests, 1), {
      paid: 14,
      pending: 3,
      under_review: 2,
      failed: 2,
      cancelled: 1,
      refunded: 1,
    }),
    entitiesByType: part(entities, { civil_association: 26, civil_foundation: 11, waqf: 5 }),
    entitiesBySize: part(entities, { small: 21, medium: 14, large: 7 }),
    entitiesByRegion: part(entities, {
      riyadh: 10,
      makkah: 9,
      eastern: 7,
      madinah: 3,
      qassim: 2,
      asir: 2,
    }).map((row) => ({ ...row, name: REGION_NAMES[row.key] })),
    assessorWorkload: [
      { key: 'a1', name: { ar: 'سارة العتيبي', en: 'Sara Alotaibi' }, open: 6, completed: 4 },
      { key: 'a2', name: { ar: 'خالد الحربي', en: 'Khalid Alharbi' }, open: 5, completed: 6 },
      { key: 'a3', name: { ar: 'ريم القحطاني', en: 'Reem Alqahtani' }, open: 4, completed: 3 },
      { key: 'a4', name: { ar: 'فهد الشمري', en: 'Fahad Alshammari' }, open: 3, completed: 5 },
      { key: 'a5', name: { ar: 'هند الدوسري', en: 'Hind Aldosari' }, open: 2, completed: 2 },
      { key: 'a6', name: { ar: 'ماجد الزهراني', en: 'Majed Alzahrani' }, open: 2, completed: 1 },
      { key: 'a7', name: { ar: 'لمى السبيعي', en: 'Lama Alsubaie' }, open: 1, completed: 3 },
    ].map((row) => ({
      ...row,
      open: scaled(row.open, scale),
      completed: scaled(row.completed, scale),
    })),
    kpiTargets: [
      {
        key: 'k1',
        name: { ar: 'نسبة الجهات المقيّمة', en: 'Assessed entities' },
        value: 62,
        target: 80,
      },
      {
        key: 'k2',
        name: { ar: 'الطلبات المنجزة في وقتها', en: 'Requests on time' },
        value: 41,
        target: 50,
      },
      { key: 'k3', name: { ar: 'رضا الجهات', en: 'Entity satisfaction' }, value: 86, target: 90 },
      {
        key: 'k4',
        name: { ar: 'الجهات الحاصلة على مستوى', en: 'Entities with a level' },
        value: 18,
        target: 30,
      },
    ],
    finance: {
      vatCollected: ((Number(collected) * 0.15) / 1.15).toFixed(2),
      refundsTotal: (scaled(2, scale) * 1150).toFixed(2),
      transfersAwaitingReview: scaled(3, scale),
      abandonedCarts: scaled(5, scale),
      receiptsIssued: requests,
    },
    award: {
      cycleYear: 2026,
      status: 'committee_assessment',
      submissionClosedOn: '2026-10-15',
      announcementOn: '2026-12-20',
      applicants: scaled(24, scale),
      qualified: scaled(15, scale),
    },
    inbox: {
      received: scaled(Math.max(4, newEntities), scale),
      awaitingReply: scaled(3, scale),
      replied: scaled(Math.max(2, newEntities - 1), scale),
      averageReplyHours: 9,
    },
    users: {
      staffActive: 14,
      entityAccounts: entities,
      mfaShare: 100,
      locked: 1,
      failedLogins: scaled(7, scale),
      completedByAssessors: completed,
    },
    storage: showsStorage ? storageOf(requests) : undefined,
  }
}

function storageOf(requests) {
  return {
    quotaBytes: STORAGE_QUOTA_GB * GIGABYTE,
    usedBytes: Math.round(STORAGE_USED_GB * GIGABYTE),
    addedInPeriodBytes: Math.round((0.4 + requests * 0.05) * GIGABYTE),
    warningPercent: 80,
    criticalPercent: 95,
    measuredAt: '2026-11-26T00:00:00+03:00',
  }
}
