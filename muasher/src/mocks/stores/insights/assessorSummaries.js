import { ACCREDITED_FIXTURE_IDS } from '../../fixtures/accreditedRequests'

const SEEDED = [
  {
    assessmentId: ACCREDITED_FIXTURE_IDS.completedOwn,
    body: 'أظهرت الجهة نضجاً واضحاً في القيادة والعمليات، مع أنظمة موثقة ومؤشرات أداء تُراجع دورياً. تحتاج إدارة المعرفة وقياس رضا العاملين إلى منهجية منتظمة، وأوصي بربط التدريب بنتائج تقييم الأداء.',
    version: 2,
    updatedAt: '2026-09-10T11:20:00Z',
  },
]

let summaries = new Map()

export function resetAssessorSummaries() {
  summaries = new Map(SEEDED.map((item) => [item.assessmentId, { ...item }]))
}

resetAssessorSummaries()

export function currentSummaryOf(assessmentId) {
  const summary = summaries.get(Number(assessmentId))
  return summary?.body ? summary : null
}

export function summaryResource(assessmentId) {
  const summary = currentSummaryOf(assessmentId)
  return summary
    ? { body: summary.body, version: summary.version, updatedAt: summary.updatedAt }
    : null
}

export function writeSummary(assessmentId, body) {
  const id = Number(assessmentId)
  const current = summaries.get(id) ?? null
  const text = typeof body === 'string' ? body.trim() : ''
  if (!text) {
    if (current) summaries.set(id, { ...current, body: null })
    return null
  }
  if (current?.body === text) return current
  const next = {
    assessmentId: id,
    body: text,
    version: (current?.version ?? 0) + 1,
    updatedAt: new Date().toISOString(),
  }
  summaries.set(id, next)
  return next
}
