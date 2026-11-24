// A column's kind sets its width and alignment in every table: the identity column is wide, a
// status medium, dates, numbers and codes narrow with tabular figures, the actions minimal. A
// column definition may name its `kind`; otherwise it follows from its key and position. A column
// that needs another width than its kind names a size: `width: 'sm' | 'md' | 'lg' | 'xl'`; an
// `optional` column gives way when its list is narrow (DataTable).
export const COLUMN_KINDS = Object.freeze({
  PRIMARY: 'primary',
  TEXT: 'text',
  STATUS: 'status',
  DATE: 'date',
  NUMBER: 'number',
  CODE: 'code',
  ACTIONS: 'actions',
})

const K = COLUMN_KINDS
const DATE_SUFFIX = 'At'

// The shared column keys of the platform's lists and their kind.
const KEY_KINDS = Object.freeze({
  actions: K.ACTIONS,
  open: K.ACTIONS,
  status: K.STATUS,
  statusRegistered: K.STATUS,
  result: K.STATUS,
  published: K.STATUS,
  level: K.STATUS,
  total: K.NUMBER,
  beforeVat: K.NUMBER,
  value: K.NUMBER,
  target: K.NUMBER,
  percent: K.NUMBER,
  entries: K.NUMBER,
  order: K.NUMBER,
  reference: K.CODE,
  number: K.CODE,
  receipt: K.CODE,
  providerRef: K.CODE,
})

// The first column names the row (the request, the entity, the user), whatever its key.
export function columnKindOf(column, index) {
  if (column.kind) return column.kind
  if (column.action || column.key === 'actions') return K.ACTIONS
  if (index === 0) return K.PRIMARY
  if (KEY_KINDS[column.key]) return KEY_KINDS[column.key]
  if (column.date || column.key.endsWith(DATE_SUFFIX)) return K.DATE
  return K.TEXT
}
