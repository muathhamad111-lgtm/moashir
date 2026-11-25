// A column's kind sets its width and alignment in every table: the identity column and text share
// the free width, statuses, dates, numbers, codes and actions keep to their content (dates, numbers
// and codes with tabular figures), and free text (a note) wraps to two lines. A column definition
// may name its `kind`; otherwise it follows from its key and position. A column may also ask for a
// floor under its content width: `width: 'sm' | 'md' | 'lg' | 'xl'`.
export const COLUMN_KINDS = Object.freeze({
  PRIMARY: 'primary',
  TEXT: 'text',
  STATUS: 'status',
  DATE: 'date',
  NUMBER: 'number',
  CODE: 'code',
  NOTE: 'note',
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
  message: K.NOTE,
  note: K.NOTE,
  notes: K.NOTE,
  description: K.NOTE,
  reason: K.NOTE,
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
