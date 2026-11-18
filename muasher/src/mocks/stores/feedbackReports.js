import { boundTree, sheetOf } from './assessments/assessmentsStore'
import {
  computeSelfResult,
  decimalWeight,
  displayEarned,
  displayPercent,
} from './assessments/selfScoring'
import { feedbackNotes } from '../fixtures/certificates'
import { certificates } from './certificatesStore'
import { currentSummaryOf } from './insights/assessorSummaries'
import { formatHijriDate } from '@/utils/datetime'

function notesOf(subId) {
  const [first, ...rest] = (feedbackNotes[subId]?.ar ?? '').split('. ')
  return {
    strengths: first || null,
    improvementPoints: rest.join('. ') || null,
  }
}

// The report shows the request's own result: the figures of its result page and certificate.
function resultOf(record, tree) {
  return record.result ?? computeSelfResult(tree, sheetOf(record), record.scaleKind)
}

function byId(units) {
  return new Map(units.map((unit) => [unit.id, unit]))
}

function figuresOf(scored) {
  return {
    weight: decimalWeight(scored.weight),
    earned: displayEarned(scored.earned),
    percent: displayPercent(scored.percent),
  }
}

function subFigures(sub, scored) {
  return {
    subId: sub.id,
    code: sub.code,
    name: sub.name,
    ...figuresOf(scored),
    ...notesOf(sub.id),
    notes: null,
  }
}

function mainFigures(main, scored) {
  const scoredSubs = byId(scored.subs)
  return {
    criterionId: main.id,
    code: main.code,
    name: main.name,
    ...figuresOf(scored),
    subs: main.subs.map((sub) => subFigures(sub, scoredSubs.get(sub.id))),
  }
}

function hijriOf(value) {
  return { ar: formatHijriDate(value, 'ar'), en: formatHijriDate(value, 'en') }
}

function certificateOf(record) {
  const found = certificates.find(
    (certificate) => certificate.request.id === record.id && certificate.status === 'issued',
  )
  return found
    ? { id: found.id, number: found.number, level: found.level, levelLabel: found.levelLabel }
    : null
}

function assessorSummaryOf(record) {
  const summary = currentSummaryOf(record.id)
  return summary ? { body: summary.body, version: summary.version } : null
}

export function feedbackReportOf(record, entity) {
  const identity = {
    assessmentId: record.id,
    number: record.number,
    path: 'accredited',
    pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
    status: record.status,
    isFrozen: false,
  }
  const tree = record.status === 'completed' ? boundTree(record) : null
  if (!tree) return { ...identity, available: false }
  const result = resultOf(record, tree)
  const scoredMains = byId(result.mains)
  const criteria = tree.mains.map((main) => mainFigures(main, scoredMains.get(main.id)))
  return {
    ...identity,
    available: true,
    source: 'assessor_assessment',
    resultVersion: 1,
    entity,
    completedAt: record.completedAt,
    completedOn: String(record.completedAt).slice(0, 10),
    completedOnHijri: hijriOf(record.completedAt),
    total: displayEarned(result.total),
    maxTotal: decimalWeight(result.totalWeight),
    percent: displayPercent(result.percent),
    certificate: certificateOf(record),
    assessorSummary: assessorSummaryOf(record),
    criteria,
  }
}
