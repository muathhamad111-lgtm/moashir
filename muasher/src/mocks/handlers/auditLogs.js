import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { auditLogsFixture } from '../fixtures/auditLogs'

const LATENCY_MS = 260
const VIEW = 'audit.view'
const EXPORT = 'audit.export'
const DATE = /^\d{4}-\d{2}-\d{2}$/
const EVENT = /^[a-z0-9_.]+$/
const RIYADH_OFFSET = '+03:00'
const CSV_COLUMNS = ['id', 'createdAt', 'event', 'actor', 'subject', 'ipAddress', 'reason']

function refusal(permission) {
  if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
  if (!sessionCan(permission) && !sessionIsSuperAdmin())
    return errorResponse(403, 'forbidden', 'This action is unauthorized.')
  return null
}

function invalidFilters(filters) {
  const fields = {}
  if (filters.from && !DATE.test(filters.from))
    fields['filter.from'] = ['Use the YYYY-MM-DD format.']
  if (filters.to && !DATE.test(filters.to)) fields['filter.to'] = ['Use the YYYY-MM-DD format.']
  if (filters.from && filters.to && filters.to < filters.from)
    fields['filter.to'] = ['The end date must be on or after the start date.']
  if (filters.event && !EVENT.test(filters.event))
    fields['filter.event'] = ['Use lower-case letters, digits, dots and underscores.']
  return Object.keys(fields).length
    ? errorResponse(422, 'validation_failed', 'The given data was invalid.', fields)
    : null
}

function boundary(day, end) {
  return day ? new Date(`${day}T${end ? '23:59:59' : '00:00:00'}${RIYADH_OFFSET}`) : null
}

function filtersOf(query) {
  const read = (key) => query.get(`filter[${key}]`) ?? ''
  return {
    from: read('from'),
    to: read('to'),
    event: read('event'),
    subjectType: read('subjectType'),
    subjectId: read('subjectId'),
    actorId: read('actorId'),
    ip: read('ip'),
  }
}

function matches(row, filters) {
  const created = new Date(row.createdAt)
  const from = boundary(filters.from, false)
  const to = boundary(filters.to, true)
  if (from && created < from) return false
  if (to && created > to) return false
  if (filters.event && row.event !== filters.event) return false
  if (filters.subjectType && row.subject?.type !== filters.subjectType) return false
  if (filters.subjectId && String(row.subject?.id) !== filters.subjectId) return false
  if (filters.actorId && String(row.actor.id) !== filters.actorId) return false
  return !filters.ip || row.ipAddress === filters.ip
}

function csvOf(rows) {
  const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`
  const lines = rows.map((row) =>
    [row.id, row.createdAt, row.event, row.actor.name, row.subject?.type, row.ipAddress, row.reason]
      .map(cell)
      .join(','),
  )
  return `\uFEFF${[CSV_COLUMNS.join(','), ...lines].join('\n')}`
}

export const auditLogsHandlers = [
  http.get('/api/v1/audit-logs', async ({ request }) => {
    await delay(LATENCY_MS)
    const refused = refusal(VIEW)
    if (refused) return refused
    const query = new URL(request.url).searchParams
    const filters = filtersOf(query)
    const invalid = invalidFilters(filters)
    if (invalid) return invalid
    const rows = auditLogsFixture.filter((row) => matches(row, filters))
    const { items, pagination } = paginate(rows, query, 25)
    return envelope(items, { pagination })
  }),

  http.get('/api/v1/audit-logs/:id', async ({ params }) => {
    await delay(LATENCY_MS)
    const refused = refusal(VIEW)
    if (refused) return refused
    const row = auditLogsFixture.find((item) => item.id === Number(params.id))
    return row ? envelope(row) : errorResponse(404, 'not_found', 'Not found.')
  }),

  http.post('/api/v1/audit-logs/export', async ({ request }) => {
    await delay(LATENCY_MS)
    const refused = refusal(EXPORT)
    if (refused) return refused
    const body = await request.json()
    if (sessionIsSuperAdmin() && !body.reason)
      return errorResponse(422, 'reason_required', 'A reason is required for this action.')
    const filters = { ...filtersOf(new URLSearchParams()), ...body.filter }
    const invalid = invalidFilters(filters)
    if (invalid) return invalid
    const rows = auditLogsFixture.filter((row) => matches(row, filters))
    return new HttpResponse(csvOf(rows), {
      status: 200,
      headers: {
        'content-type': 'text/csv; charset=UTF-8',
        'content-disposition': 'attachment; filename="audit-log-20261105-060000.csv"',
        'x-export-row-count': String(rows.length),
      },
    })
  }),
]
