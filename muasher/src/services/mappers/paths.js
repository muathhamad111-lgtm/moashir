import { toRequestStatus } from './accreditedStatus'

export function toPeriod(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    key: raw.key ?? '',
    cadence: raw.cadence ?? null,
    label: raw.label ?? null,
    startsOn: raw.startsOn ?? null,
    endsOn: raw.endsOn ?? null,
    startsAt: raw.startsAt ?? null,
    endsAt: raw.endsAt ?? null,
    isShortened: raw.isShortened === true,
    noticeOn: raw.noticeOn ?? null,
  }
}

export function toPriceQuote(raw) {
  if (!raw) return null
  return {
    priceBeforeVat: raw.priceBeforeVat ?? null,
    vatRate: raw.vatRate ?? null,
    vatAmount: raw.vatAmount ?? null,
    total: raw.total ?? null,
    currency: raw.currency ?? 'SAR',
  }
}

export function toPathPricing(raw) {
  if (!raw) return null
  return {
    price: toPriceQuote(raw.price),
    isFree: raw.isFree === true,
    availability: raw.availability ?? null,
  }
}

export function toCertificateSummary(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    level: raw.level ?? null,
    status: raw.status ?? null,
    issuedAt: raw.issuedAt ?? null,
    validUntil: raw.validUntil ?? null,
    validUntilAt: raw.validUntilAt ?? null,
    isExpired: raw.isExpired === true,
    validity: raw.validity ?? null,
    daysUntilExpiry: raw.daysUntilExpiry ?? null,
  }
}

export function toKitLine(raw) {
  return { status: raw?.status ?? null, requestId: raw?.requestId ?? null }
}

export function toRequestPeriod(raw = {}) {
  return {
    periodState: raw.periodState ?? null,
    period: toPeriod(raw.period),
    periodLabel: raw.periodLabel ?? null,
  }
}

function toAwaitingPayment(raw) {
  if (!raw) return null
  return {
    paymentId: raw.paymentId ?? null,
    reference: raw.reference ?? '',
    status: raw.status ?? null,
    channel: raw.channel ?? null,
    expiresAt: raw.expiresAt ?? null,
  }
}

function listedOf(raw) {
  return raw.isListed !== false
}

function toSelfPath(raw) {
  if (!raw) return null
  const draft = raw.openDraft
  const result = raw.lastResult
  return {
    pricing: toPathPricing(raw.pricing),
    isListed: listedOf(raw),
    awaitingPayment: toAwaitingPayment(raw.awaitingPayment),
    openDraft: draft
      ? {
          id: draft.id ?? null,
          number: draft.number ?? '',
          status: toRequestStatus(draft.status),
          updatedAt: draft.updatedAt ?? null,
          progress: draft.progress
            ? {
                subsComplete: Number(draft.progress.subsComplete ?? 0),
                subsTotal: Number(draft.progress.subsTotal ?? 0),
              }
            : null,
        }
      : null,
    lastResult: result
      ? {
          assessmentId: result.assessmentId ?? null,
          number: result.number ?? '',
          percent: result.percent ?? null,
          completedAt: result.completedAt ?? null,
        }
      : null,
  }
}

function toOpenAccredited(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    status: toRequestStatus(raw.status),
    needsCompletion: raw.needsCompletion === true,
    completionDeadline: raw.completionDeadline ?? null,
    remainingCount: raw.remainingCount ?? null,
    submittedAt: raw.submittedAt ?? null,
    periodState: raw.periodState ?? null,
    periodLabel: raw.periodLabel ?? null,
  }
}

function toAccreditedResult(raw) {
  if (!raw) return null
  return {
    assessmentId: raw.assessmentId ?? null,
    number: raw.number ?? '',
    percent: raw.percent ?? null,
    level: raw.level ?? null,
    completedAt: raw.completedAt ?? null,
    ...toRequestPeriod(raw),
    certificate: toCertificateSummary(raw.certificate),
  }
}

function toPeriodFields(raw) {
  return {
    isLive: raw.isLive === true,
    cadence: raw.cadence ?? null,
    currentPeriod: toPeriod(raw.currentPeriod),
    periodStatus: raw.periodStatus ?? null,
    nextPeriod: toPeriod(raw.nextPeriod),
    nextSubmissionFrom: raw.nextSubmissionFrom ?? null,
  }
}

function toAccreditedPath(raw) {
  if (!raw) return null
  return {
    pricing: toPathPricing(raw.pricing),
    isListed: listedOf(raw),
    ...toPeriodFields(raw),
    awaitingPayment: toAwaitingPayment(raw.awaitingPayment),
    openRequest: toOpenAccredited(raw.openRequest),
    lastResult: toAccreditedResult(raw.lastResult),
    kit: toKitLine(raw.kit),
  }
}

function toCycle(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    name: raw.name ?? null,
    submissionStart: raw.submissionStart ?? null,
    submissionEnd: raw.submissionEnd ?? null,
    announcementAt: raw.announcementAt ?? null,
    minEntityAgeYears: raw.minEntityAgeYears ?? null,
    regions: (raw.regions ?? []).map((region) => ({
      id: region.id ?? null,
      code: region.code ?? '',
      name: region.name ?? null,
    })),
    entryFee: toPriceQuote(raw.entryFee),
  }
}

function toAwardPath(raw) {
  if (!raw) return null
  const open = raw.openRequest
  const result = raw.lastResult
  return {
    pricing: toPathPricing(raw.pricing),
    isListed: listedOf(raw),
    windowState: raw.windowState ?? null,
    cycle: toCycle(raw.cycle),
    nextCycle: raw.nextCycle
      ? {
          id: raw.nextCycle.id ?? null,
          name: raw.nextCycle.name ?? null,
          submissionStart: raw.nextCycle.submissionStart ?? null,
          entryFee: toPriceQuote(raw.nextCycle.entryFee),
        }
      : null,
    eligibility: {
      eligible: raw.eligibility?.eligible === true,
      reason: raw.eligibility?.reason ?? null,
    },
    awaitingPayment: toAwaitingPayment(raw.awaitingPayment),
    openRequest: open
      ? {
          id: open.id ?? null,
          number: open.number ?? '',
          status: toRequestStatus(open.status),
          isOpen: open.isOpen === true,
          payment: toRequestPayment(open.payment),
        }
      : null,
    windowClosedBeforePayment: raw.windowClosedBeforePayment === true,
    announced: raw.announced === true,
    lastResult: result
      ? {
          assessmentId: result.assessmentId ?? null,
          number: result.number ?? '',
          percent: result.percent ?? null,
          announcedAt: result.announcedAt ?? null,
        }
      : null,
  }
}

export function toPathsWidget(raw) {
  if (!raw) return null
  return {
    self: toSelfPath(raw.self),
    accredited: toAccreditedPath(raw.accredited),
    award: toAwardPath(raw.award),
  }
}

export function toLevelWidget(raw) {
  if (!raw) return null
  return { level: raw.level ?? null, certificate: toCertificateSummary(raw.certificate) }
}

function toTarget(raw) {
  return {
    route: raw?.route ?? null,
    params: raw?.params ?? {},
    query: raw?.query ?? {},
    href: raw?.href ?? null,
  }
}

function toStepContext(raw) {
  return {
    assessmentId: raw?.assessmentId ?? null,
    number: raw?.number ?? null,
    status: toRequestStatus(raw?.status ?? null),
    paymentId: raw?.paymentId ?? null,
    isPaid: raw?.isPaid ?? null,
    deadlineAt: raw?.deadlineAt ?? null,
    periodEndsOn: raw?.periodEndsOn ?? null,
    cycleId: raw?.cycleId ?? null,
    paymentReference: raw?.paymentReference ?? null,
    paymentChannel: raw?.paymentChannel ?? null,
    paymentStatus: raw?.paymentStatus ?? null,
    paymentExpiresAt: raw?.paymentExpiresAt ?? null,
  }
}

export function toNextStep(raw) {
  if (!raw) return null
  return {
    rank: Number(raw.rank ?? 0),
    key: raw.key ?? '',
    path: raw.path ?? null,
    target: toTarget(raw.target),
    context: toStepContext(raw.context),
  }
}

export function toPeriodic(raw) {
  if (!raw) return null
  return {
    ...toPeriodFields(raw),
    rolloutFrom: raw.rolloutFrom ?? null,
    cadenceEffectiveFrom: raw.cadenceEffectiveFrom ?? null,
    periodRequest: raw.periodRequest
      ? {
          id: raw.periodRequest.id ?? null,
          number: raw.periodRequest.number ?? '',
          status: toRequestStatus(raw.periodRequest.status),
        }
      : null,
  }
}

export function toSubmissionGate(raw) {
  if (!raw) return null
  return {
    canSubmitNow: raw.canSubmitNow === true,
    reason: raw.reason ?? null,
    nextSubmissionFrom: raw.nextSubmissionFrom ?? null,
  }
}

export function toApplyGate(raw) {
  if (!raw) return null
  return {
    canStart: raw.canStart === true,
    reason: raw.reason ?? null,
    warning: raw.warning ?? null,
  }
}

function toRequestPayment(raw) {
  if (!raw) return null
  return {
    reference: raw.reference ?? '',
    channel: raw.channel ?? null,
    status: raw.status ?? null,
    isTest: raw.isTest === true,
    paidAt: raw.paidAt ?? null,
    receipt: raw.receipt
      ? {
          number: raw.receipt.number ?? '',
          status: raw.receipt.status ?? null,
          isTest: raw.receipt.isTest === true,
        }
      : null,
  }
}

export function toAwardCycleRef(raw) {
  return raw ? { id: raw.id ?? null, name: raw.name ?? null } : null
}

export function toPathRowFields(raw = {}) {
  return {
    rowType: raw.rowType ?? 'request',
    rowKey: raw.rowKey ?? (raw.id ? `request-${raw.id}` : null),
    periodStatus: raw.periodStatus ?? null,
    ...toRequestPeriod(raw),
    payment: toRequestPayment(raw.payment),
    awardCycle: toAwardCycleRef(raw.awardCycle),
    level: raw.level ?? null,
    certificate: toCertificateSummary(raw.certificate),
    kit: raw.kit ? toKitLine(raw.kit) : null,
    availableTabs: raw.availableTabs ?? null,
    defaultTab: raw.defaultTab ?? null,
  }
}
