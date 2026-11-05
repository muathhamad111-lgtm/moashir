import { FEEDBACK_FIELDS } from '@/constants/accredited'
import { fromCanonicalStatus } from '@/utils/canonicalStatus'
import { toAssessment, toAssessmentResult, toCriteriaUpdate } from './assessments'
import { toCompletionStatus } from './accreditedStatus'

export function toEvidence(raw = {}) {
  return {
    id: raw.id ?? null,
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
    subCriterionId: raw.subCriterionId ?? null,
    code: raw.code ?? '',
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

function toMissing(raw) {
  return {
    missingCv: (raw?.missingCv ?? []).map(toMissingItem),
    missingAttachments: (raw?.missingAttachments ?? []).map(toMissingItem),
  }
}

export function toCvSheet(raw) {
  return Object.fromEntries(Object.entries(raw ?? {}).map(([subId, text]) => [subId, text ?? '']))
}

export function toAccreditedForm(raw = {}) {
  return {
    assessment: toAssessment(raw.assessment),
    criteria: raw.criteria ?? null,
    cv: toCvSheet(raw.cv),
    evidence: toEvidenceList(raw.evidence),
    missing: toMissing(raw.missing),
    completionRequest: toCompletionRequest(raw.completionRequest),
    uploadLimits: toUploadLimits(raw.uploadLimits),
    criteriaUpdate: toCriteriaUpdate(raw.criteriaUpdate),
  }
}

export function toCvSaved(raw = {}) {
  return { assessment: toAssessment(raw.assessment), cv: toCvSheet(raw.cv) }
}

function toAwaitingPayment(raw) {
  if (!raw) return null
  return {
    paymentId: raw.paymentId ?? null,
    reference: raw.reference ?? '',
    status: raw.status ?? null,
    expiresAt: raw.expiresAt ?? null,
  }
}

export function toAccreditedCurrent(raw = {}) {
  return {
    awaitingPayment: toAwaitingPayment(raw.awaitingPayment),
    active: raw.active ? toAssessment(raw.active) : null,
    latestClosed: raw.latestClosed ? toAssessment(raw.latestClosed) : null,
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

export function toServicePrices(raw = {}) {
  return { accredited: toPricing(raw.accredited), bag: toPricing(raw.bag) }
}

export function toAssessmentSettings(raw = {}) {
  return {
    accreditedReviewSlaDays: raw.accreditedReviewSlaDays ?? null,
    accreditedReviewSlaLimits: {
      min: raw.accreditedReviewSlaLimits?.min ?? 1,
      max: raw.accreditedReviewSlaLimits?.max ?? 120,
    },
    uploadMaxFileMb: raw.uploadMaxFileMb ?? null,
    evidenceAllowedTypes: raw.evidenceAllowedTypes ?? [],
    evidenceMaxFilesPerSub: raw.evidenceMaxFilesPerSub ?? null,
    evidenceTypeCatalog: raw.evidenceTypeCatalog ?? [],
  }
}
