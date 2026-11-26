import { isOverdue } from '../accredited/accreditedRecords'

// The review clock of an award request on the filtering and secretary lists, as
// `ReviewClockPresenter::of` serves it.
export function reviewClockOf(record) {
  return {
    submittedAt: record.submittedAt,
    dueAt: record.dueAt ?? null,
    isOverdue: isOverdue(record),
    completedAt: record.completedAt ?? null,
  }
}
