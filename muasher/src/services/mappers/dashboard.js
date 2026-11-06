function toActionContext(raw) {
  if (!raw) return null
  return {
    assessmentId: raw.assessmentId ?? null,
    paymentId: raw.paymentId ?? null,
    messageId: raw.messageId ?? null,
    reference: raw.reference ?? null,
    remainingSubs: raw.remainingSubs ?? null,
    percent: raw.percent ?? null,
  }
}

export function toActionItem(raw = {}) {
  return {
    key: raw.key ?? '',
    count: Number(raw.count ?? 0),
    severity: raw.severity ?? 'info',
    link: raw.link ?? null,
    dueAt: raw.dueAt ?? null,
    context: toActionContext(raw.context),
  }
}

export function toActionItems(items) {
  return (items ?? []).map(toActionItem).filter((item) => item.key && item.count > 0)
}

function widgetData(raw, key) {
  return (raw?.widgets ?? []).find((widget) => widget.key === key)?.data ?? null
}

function toLatestResult(raw) {
  if (!raw) return null
  return {
    assessmentId: raw.assessmentId ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    percent: raw.percent ?? null,
    completedAt: raw.completedAt ?? null,
  }
}

function cardValue(raw, key) {
  return (raw?.cards ?? []).find((card) => card.key === key)?.value ?? null
}

function toCount(value) {
  return value === null || value === undefined ? null : Number(value)
}

export function toEntityBoard(raw = {}) {
  return {
    acceptedEvidenceCount: toCount(cardValue(raw, 'accepted_evidence_count')),
    latestResult: toLatestResult(widgetData(raw, 'latest_result')),
    paymentsCard: widgetData(raw, 'payments_card'),
    actionCenter: toActionItems(raw?.actionCenter),
  }
}

function toCard(raw = {}) {
  return {
    key: raw.key ?? '',
    value: raw.value ?? null,
    format: raw.format ?? 'count',
    link: raw.link ?? null,
  }
}

function toWidget(raw = {}) {
  return { key: raw.key ?? '', data: raw.data ?? null }
}

export function toDashboardSummary(raw = {}) {
  return {
    board: raw.board ?? null,
    landingBoard: raw.landingBoard ?? null,
    availableBoards: raw.availableBoards ?? [],
    cards: (raw.cards ?? []).map(toCard).filter((card) => card.key),
    widgets: (raw.widgets ?? []).map(toWidget).filter((widget) => widget.key),
    actionCenter: toActionItems(raw.actionCenter),
    generatedAt: raw.generatedAt ?? null,
  }
}
