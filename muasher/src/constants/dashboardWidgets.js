export const WIDGET_KINDS = Object.freeze({
  BARS: 'bars',
  TABLE: 'table',
  CRITERIA: 'criteria',
  TIMELINE: 'timeline',
  FACTS: 'facts',
})

export const BAR_LABELS = Object.freeze({
  NAME: 'name',
  PATH: 'path',
  SECTOR: 'sector',
  DECADE: 'decade',
  BAND: 'band',
  HISTOGRAM: 'histogram',
  PURPOSE: 'purpose',
})

export const COLUMN_FORMATS = Object.freeze({
  TEXT: 'text',
  LOCALIZED: 'localized',
  NUMBER: 'number',
  PERCENT: 'percent',
  MONEY: 'money',
  DATE: 'date',
  DATETIME: 'datetime',
  STATUS: 'status',
  PATH: 'path',
  REFERENCE: 'reference',
  BOOLEAN: 'boolean',
  TYPE: 'type',
})

const F = COLUMN_FORMATS

function column(key, format = F.TEXT) {
  return Object.freeze({ key, format })
}

function bars(label, { amountKey = null } = {}) {
  return Object.freeze({ kind: WIDGET_KINDS.BARS, label, amountKey })
}

function table(...columns) {
  return Object.freeze({ kind: WIDGET_KINDS.TABLE, columns: Object.freeze(columns) })
}

function facts(...columns) {
  return Object.freeze({ kind: WIDGET_KINDS.FACTS, columns: Object.freeze(columns) })
}

const MEMBER_CYCLES = table(
  column('name', F.LOCALIZED),
  column('status', F.STATUS),
  column('submissionEnd', F.DATE),
  column('committeeStart', F.DATE),
  column('committeeEnd', F.DATE),
  column('requests', F.NUMBER),
)

const COMMITTEE_CYCLES = table(
  column('name', F.LOCALIZED),
  column('status', F.STATUS),
  column('committeeStart', F.DATE),
  column('committeeEnd', F.DATE),
  column('assigned', F.NUMBER),
  column('completed', F.NUMBER),
  column('remaining', F.NUMBER),
)

export const DASHBOARD_WIDGETS = Object.freeze({
  entities_by_region: bars(BAR_LABELS.NAME),
  by_region: bars(BAR_LABELS.NAME),
  entities_by_sector: bars(BAR_LABELS.SECTOR),
  entities_by_founding_decade: bars(BAR_LABELS.DECADE),
  entities_by_governance: bars(BAR_LABELS.BAND),
  score_histogram: bars(BAR_LABELS.HISTOGRAM),
  requests_by_path: bars(BAR_LABELS.PATH),
  by_service: bars(BAR_LABELS.PURPOSE, { amountKey: 'total' }),
  lowest_criteria: Object.freeze({ kind: WIDGET_KINDS.CRITERIA }),
  weakest_criteria: Object.freeze({ kind: WIDGET_KINDS.CRITERIA }),
  activity: Object.freeze({ kind: WIDGET_KINDS.TIMELINE }),
  pipeline: table(column('path', F.PATH), column('status', F.STATUS), column('count', F.NUMBER)),
  assessors: table(column('name'), column('active', F.NUMBER), column('completed', F.NUMBER)),
  due_soon: table(
    column('number', F.REFERENCE),
    column('path', F.PATH),
    column('entityName', F.LOCALIZED),
    column('status', F.STATUS),
    column('dueAt', F.DATETIME),
    column('isOverdue', F.BOOLEAN),
  ),
  top_entities: table(
    column('number', F.REFERENCE),
    column('entityName', F.LOCALIZED),
    column('status', F.STATUS),
    column('averagePercent', F.PERCENT),
  ),
  latest_tasks: table(
    column('status', F.STATUS),
    column('startedAt', F.DATETIME),
    column('submittedAt', F.DATETIME),
    column('updatedAt', F.DATETIME),
  ),
  latest_edits: table(
    column('type', F.TYPE),
    column('title', F.LOCALIZED),
    column('updatedAt', F.DATETIME),
  ),
  daily_payments: table(
    column('date', F.DATE),
    column('count', F.NUMBER),
    column('total', F.MONEY),
  ),
  requests: table(
    column('number', F.REFERENCE),
    column('path', F.PATH),
    column('status', F.STATUS),
    column('createdAt', F.DATE),
  ),
  criteria_progress: table(
    column('code', F.REFERENCE),
    column('name', F.LOCALIZED),
    column('percent', F.PERCENT),
  ),
  latest_result: facts(
    column('number', F.REFERENCE),
    column('path', F.PATH),
    column('percent', F.PERCENT),
    column('completedAt', F.DATE),
  ),
  published_version: facts(
    column('versionNumber', F.NUMBER),
    column('label', F.LOCALIZED),
    column('publishedAt', F.DATE),
  ),
  draft_version: facts(
    column('versionNumber', F.NUMBER),
    column('label', F.LOCALIZED),
    column('problems', F.NUMBER),
    column('updatedAt', F.DATE),
  ),
  payments_card: facts(column('receiptsCount', F.NUMBER)),
})

export const BOARD_SPECIFIC_WIDGETS = Object.freeze({
  committee_assessor: Object.freeze({ cycles: COMMITTEE_CYCLES }),
  award_secretary: Object.freeze({ cycles: MEMBER_CYCLES }),
  filtering_officer: Object.freeze({ cycles: MEMBER_CYCLES }),
})

export function widgetDefinition(board, key) {
  return BOARD_SPECIFIC_WIDGETS[board]?.[key] ?? DASHBOARD_WIDGETS[key] ?? null
}
