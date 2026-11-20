import { FEEDBACK_FIELDS } from '@/constants/accredited'
import { fromCanonicalStatus } from '@/utils/canonicalStatus'
import { toAssessment, toAssessmentResult, toCriteriaUpdate } from './assessments'
import { toCompletionStatus } from './accreditedStatus'
import { toApplyGate, toPathPricing, toPeriodic, toRequestPeriod, toSubmissionGate } from './paths'

export function toEvidence(raw = {}) {
  return {
    id: raw.id ?? null,
    level: raw.level ?? 'sub',
    mainCriterionId: raw.mainCriterionId ?? null,
    subCriterionId: raw.subCriterionId ?? null,
    code: raw.code ?? '',
    attachmentKey: raw.attachmentKey ?? null,
    fileName: raw.fileName ?? '',
    mimeType: raw.mimeType ?? '',
    sizeBytes: raw.sizeBytes ?? 0,
    scanStatus: fromCanonicalStatus(raw.scanStatus ?? null),
    uploadedAt: raw.uploadedAt ?? null,
  }
}

export function toEvidenceList(items) {
  return (items ?? []).map(toEvidence)
}

function toCompletionItem(raw) {
  return {
    mainCriterionId: raw.mainCriterionId ?? null,
    subCriterionId: raw.subCriterionId ?? null,
    code: raw.code ?? '',
    name: raw.name ?? null,
    kind: raw.kind ?? null,
    note: raw.note ?? null,
  }
}

export function toCompletionRequest(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    status: toCompletionStatus(raw.status),
    notes: raw.notes ?? '',
    items: (raw.items ?? []).map(toCompletionItem),
    deadlineAt: raw.deadlineAt ?? null,
    expiryOutcome: raw.expiryOutcome ?? null,
    requestedAt: raw.requestedAt ?? null,
    resolvedAt: raw.resolvedAt ?? null,
  }
}

function toUploadLimits(raw) {
  return {
    maxFileMb: raw?.maxFileMb ?? null,
    allowedTypes: raw?.allowedTypes ?? [],
    maxFilesPerSub: raw?.maxFilesPerSub ?? null,
  }
}

function toMissingItem(raw) {
  return {
    subCriterionId: raw.subCriterionId ?? null,
    code: raw.code ?? '',
    attachmentKey: raw.attachmentKey ?? null,
  }
}

function toMissingMainItem(raw) {
  return {
    mainCriterionId: raw.mainCriterionId ?? null,
    code: raw.code ?? '',
    attachmentKey: raw.attachmentKey ?? null,
  }
}

function toMissing(raw) {
  return {
    missingCv: (raw?.missingCv ?? []).map(toMissingItem),
    missingAttachments: (raw?.missingAttachments ?? []).map(toMissingItem),
    missingMainAttachments: (raw?.missingMainAttachments ?? []).map(toMissingMainItem),
  }
}

export function toCvSheet(raw) {
  return Object.fromEntries(Object.entries(raw ?? {}).map(([subId, text]) => [subId, text ?? '']))
}

export function toAccreditedForm(raw = {}) {
  return {
    assessment: { ...toAssessment(raw.assessment), ...toRequestPeriod(raw.assessment ?? {}) },
    criteria: raw.criteria ?? null,
    cv: toCvSheet(raw.cv),
    evidence: toEvidenceList(raw.evidence),
    missing: toMissing(raw.missing),
    completionRequest: toCompletionRequest(raw.completionRequest),
    uploadLimits: toUploadLimits(raw.uploadLimits),
    criteriaUpdate: toCriteriaUpdate(raw.criteriaUpdate),
    passedStations: toPassedStations(raw.passedStations),
  }
}

export function toPassedStations(raw) {
  return (raw ?? []).map(Number)
}

export function toCvSaved(raw = {}) {
  return { assessment: toAssessment(raw.assessment), cv: toCvSheet(raw.cv) }
}

function toAwaitingPayment(raw) {
  if (!raw) return null
  return {
    paymentId: raw.paymentId ?? null,
    reference: raw.reference ?? '',
    channel: raw.channel ?? 'electronic',
    status: raw.status ?? null,
    expiresAt: raw.expiresAt ?? null,
  }
}

function toRemainingSub(raw = {}) {
  return {
    subCriterionId: raw.subCriterionId ?? null,
    code: raw.code ?? '',
    name: raw.name ?? null,
    cvMissing: raw.cvMissing === true,
    attachmentsRequired: Number(raw.attachmentsRequired ?? 0),
    attachmentsUploaded: Number(raw.attachmentsUploaded ?? 0),
  }
}

export function toSubmissionProgress(raw) {
  if (!raw) return null
  return {
    subsTotal: Number(raw.subsTotal ?? 0),
    subsComplete: Number(raw.subsComplete ?? 0),
    remaining: (raw.remaining ?? []).map(toRemainingSub),
  }
}

function toActiveRequest(raw) {
  if (!raw) return null
  return {
    ...toAssessment(raw),
    ...toRequestPeriod(raw),
    progress: toSubmissionProgress(raw.progress),
  }
}

export function toAccreditedCurrent(raw = {}) {
  return {
    awaitingPayment: toAwaitingPayment(raw.awaitingPayment),
    active: toActiveRequest(raw.active),
    latestClosed: raw.latestClosed ? toAssessment(raw.latestClosed) : null,
    periodic: toPeriodic(raw.periodic),
    submission: toSubmissionGate(raw.submission),
    apply: toApplyGate(raw.apply),
    pricing: toPathPricing(raw.pricing),
  }
}

export function toFeedbackFields(raw) {
  return Object.fromEntries(FEEDBACK_FIELDS.map((field) => [field, raw?.[field] ?? null]))
}

function toResultFeedback(raw) {
  return {
    subId: raw.subId ?? null,
    code: raw.code ?? '',
    name: raw.name ?? null,
    percent: raw.percent ?? null,
    ...toFeedbackFields(raw),
  }
}

export function toAccreditedResult(raw = {}) {
  const result = toAssessmentResult(raw)
  if (!result.available) return result
  return { ...result, feedback: (raw.feedback ?? []).map(toResultFeedback) }
}

export function toBundle(raw = {}) {
  return {
    id: raw.id ?? null,
    assessmentId: raw.assessmentId ?? null,
    status: raw.status ?? null,
    fileCount: raw.fileCount ?? 0,
    sizeBytes: raw.sizeBytes ?? 0,
    isDownloadable: raw.isDownloadable === true,
    requestedAt: raw.requestedAt ?? null,
    readyAt: raw.readyAt ?? null,
    expiresAt: raw.expiresAt ?? null,
  }
}

function toPricing(raw) {
  if (!raw) return null
  return {
    priceBeforeVat: raw.priceBeforeVat ?? null,
    vatRate: raw.vatRate ?? null,
    vatAmount: raw.vatAmount ?? null,
    total: raw.total ?? null,
    currency: raw.currency ?? 'SAR',
  }
}

function toPathPricingMap(raw) {
  if (!raw || typeof raw !== 'object') return {}
  return Object.fromEntries(Object.entries(raw).map(([key, value]) => [key, toPathPricing(value)]))
}

export function toServicePrices(raw = {}) {
  return {
    accredited: toPricing(raw.accredited),
    bag: toPricing(raw.bag),
    paths: toPathPricingMap(raw.paths),
  }
}

export function toAssessmentSettings(raw = {}) {
  return {
    generalUploadMaxFileMb: raw.generalUploadMaxFileMb ?? null,
    advancedMinEntityAgeYears: raw.advancedMinEntityAgeYears ?? null,
    advancedDisqualificationScore: raw.advancedDisqualificationScore ?? null,
    committeeEntityDataVisibility: raw.committeeEntityDataVisibility ?? null,
    scaleKind: raw.scaleKind ?? null,
    publishedScaleKind: raw.publishedVersion?.scaleKind ?? null,
  }
}
