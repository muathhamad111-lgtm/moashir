import { boundTree } from './assessments/assessmentsStore'
import { feedbackNotes, feedbackRatios } from '../fixtures/certificates'
import { certificates } from './certificatesStore'

const DEFAULT_RATIO = 0.72
const HIJRI = { ar: '10 ربيع الأول 1448 هـ', en: '10 Rabi al-Awwal 1448 AH' }

function fixed(value) {
  return Number(value).toFixed(2)
}

function notesOf(subId) {
  const [first, ...rest] = (feedbackNotes[subId]?.ar ?? '').split('. ')
  return {
    strengths: first || null,
    improvementPoints: rest.join('. ') || null,
  }
}

function subFigures(sub) {
  const ratio = feedbackRatios[sub.id] ?? DEFAULT_RATIO
  const weight = Number(sub.weight ?? 0)
  return {
    subId: sub.id,
    code: sub.code,
    name: sub.name,
    weight: fixed(weight),
    earned: fixed(weight * ratio),
    percent: fixed(ratio * 100),
    ...notesOf(sub.id),
    notes: null,
  }
}

function mainFigures(main) {
  const subs = main.subs.map(subFigures)
  const weight = subs.reduce((sum, sub) => sum + Number(sub.weight), 0)
  const earned = subs.reduce((sum, sub) => sum + Number(sub.earned), 0)
  return {
    criterionId: main.id,
    code: main.code,
    name: main.name,
    weight: fixed(weight),
    earned: fixed(earned),
    percent: fixed(weight ? (earned / weight) * 100 : 0),
    subs,
  }
}

function certificateOf(record) {
  const found = certificates.find(
    (certificate) => certificate.request.id === record.id && certificate.status === 'issued',
  )
  return found
    ? { id: found.id, number: found.number, level: found.level, levelLabel: found.levelLabel }
    : null
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
  const criteria = tree.mains.map(mainFigures)
  const total = criteria.reduce((sum, main) => sum + Number(main.earned), 0)
  const maxTotal = criteria.reduce((sum, main) => sum + Number(main.weight), 0)
  return {
    ...identity,
    available: true,
    source: 'assessor_assessment',
    resultVersion: 1,
    entity,
    completedAt: record.completedAt,
    completedOn: String(record.completedAt).slice(0, 10),
    completedOnHijri: HIJRI,
    total: fixed(total),
    maxTotal: fixed(maxTotal),
    percent: fixed(maxTotal ? (total / maxTotal) * 100 : 0),
    certificate: certificateOf(record),
    criteria,
  }
}
