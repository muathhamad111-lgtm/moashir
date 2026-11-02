import { boundTree, sheetOf } from '../assessments/assessmentsStore'
import { computeSelfResult } from '../assessments/selfScoring'

export function recordSnapshot(record) {
  const tree = boundTree(record)
  record.result = {
    version: (record.result?.version ?? 0) + 1,
    criteriaVersionId: record.criteriaVersionId,
    scaleKind: record.scaleKind,
    ...computeSelfResult(tree, sheetOf(record), record.scaleKind),
  }
  return record.result
}
