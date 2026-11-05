import { findSub } from '../criteriaStore'
import { boundSubsOf, gapsOf, openCompletionOf } from './accreditedRecords'

const PROGRESS_STATES = ['draft', 'needs-completion']
const NEEDS_COMPLETION = 'needs-completion'

function scopeOf(record, subs) {
  if (record.status !== NEEDS_COMPLETION) return subs
  const requested = new Set((openCompletionOf(record)?.items ?? []).map((item) => item.subCode))
  return subs.filter((sub) => requested.has(sub.code))
}

function remainingOf(sub, gaps) {
  const cvMissing = gaps.missingCv.some((item) => item.code === sub.code)
  const missingSlots = gaps.missingAttachments.filter((item) => item.code === sub.code).length
  if (!cvMissing && !missingSlots) return null
  return {
    subCriterionId: sub.id,
    code: sub.code,
    name: findSub(sub.id)?.name ?? null,
    cvMissing,
    attachmentsRequired: sub.requiredKeys.length,
    attachmentsUploaded: sub.requiredKeys.length - missingSlots,
  }
}

function byCode(left, right) {
  return left.code.localeCompare(right.code, 'en', { numeric: true })
}

export function accreditedProgressOf(record) {
  if (!PROGRESS_STATES.includes(record.status)) return null
  const scope = scopeOf(record, boundSubsOf(record))
  const gaps = gapsOf(record)
  const remaining = scope
    .map((sub) => remainingOf(sub, gaps))
    .filter(Boolean)
    .sort(byCode)
  return {
    subsTotal: scope.length,
    subsComplete: scope.length - remaining.length,
    remaining,
  }
}
