import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { seedBagRequests, bagRequestEntities } from '../fixtures/bagRequests'
import { usersFixture } from '../fixtures/users'
import { failsRandomly } from '../contract/faults'

let requests = seedBagRequests()

export function resetBagRequests() {
  requests = seedBagRequests()
}

let historySeed = 90000
function nextHistoryId() {
  historySeed += 1
  return `bah-${historySeed}`
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}
function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}
const maybeFail = failsRandomly(0.06)

function isEvaluator(user) {
  return (user.roles ?? []).includes('assessor') && user.status === 'active'
}
function evaluatorName(id) {
  if (!id) return null
  return usersFixture.find((u) => u.id === id)?.name ?? null
}
function entityName(id) {
  return bagRequestEntities[id] ?? { ar: 'جهة غير مسماة', en: 'Unnamed entity' }
}

function toRow(r) {
  return {
    id: r.id,
    entityId: r.entityId,
    entityName: entityName(r.entityId),
    status: r.status,
    referenceSnapshot: { ...r.referenceSnapshot },
    selfScorePercent: r.selfScorePercent ?? null,
    advancedScorePercent: r.advancedScorePercent ?? null,
    hasPreviousBag: Boolean(r.previousBagId),
    previousBagId: r.previousBagId ?? null,
    assignedEvaluatorId: r.assignedEvaluatorId ?? null,
    assignedEvaluatorName: evaluatorName(r.assignedEvaluatorId),
    paidAt: r.paidAt,
    assignedAt: r.assignedAt ?? null,
    startedAt: r.startedAt ?? null,
    completedAt: r.completedAt ?? null,
  }
}

function toDetail(r) {
  return {
    ...toRow(r),
    assignmentHistory: r.assignmentHistory.map((h) => ({
      ...h,
      evaluatorName: evaluatorName(h.evaluatorId),
    })),
  }
}

function stats() {
  const byStatus = {}
  for (const r of requests) byStatus[r.status] = (byStatus[r.status] ?? 0) + 1
  return {
    total: requests.length,
    byStatus,
    awaiting: byStatus['awaiting-assignment'] ?? 0,
  }
}

function matchesSearch(r, needle) {
  const name = entityName(r.entityId)
  return (
    (name.ar ?? '').toLowerCase().includes(needle) ||
    (name.en ?? '').toLowerCase().includes(needle) ||
    r.id.toLowerCase().includes(needle) ||
    String(r.assignedEvaluatorId ?? '').includes(needle)
  )
}

function paginate(list, url) {
  const search = (url.searchParams.get('search') ?? '').trim().toLowerCase()
  const statusFilter = url.searchParams.get('filter[status]')
  const page = Number(url.searchParams.get('page') ?? 1)
  const perPage = Number(url.searchParams.get('perPage') ?? 10)
  let items = list
  if (statusFilter) items = items.filter((r) => r.status === statusFilter)
  if (search) items = items.filter((r) => matchesSearch(r, search))
  const total = items.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const start = (page - 1) * perPage
  const paged = items.slice(start, start + perPage).map(toRow)
  return { paged, meta: { pagination: { page, perPage: perPage, total, lastPage: lastPage } } }
}

export const bagRequestsHandlers = [
  http.get('/api/v1/bag/requests/evaluators', async () => {
    await delay(220)
    if (maybeFail(0.05)) return errorResponse('Failed to load evaluators', 503)
    const list = usersFixture.filter(isEvaluator).map((u) => ({ id: u.id, name: u.name }))
    return envelope(list)
  }),

  http.get('/api/v1/bag/requests', async ({ request }) => {
    await delay(400)
    if (maybeFail(0.07)) return errorResponse('Failed to load the bag requests queue', 503)
    const url = new URL(request.url)
    const { paged, meta } = paginate(requests, url)
    return envelope(paged, { ...meta, stats: stats() })
  }),

  http.get('/api/v1/bag/evaluator/requests', async ({ request }) => {
    await delay(400)
    if (maybeFail(0.07)) return errorResponse('Failed to load your bag requests', 503)
    const url = new URL(request.url)
    const evaluatorId = Number(url.searchParams.get('evaluatorId'))
    const mine = requests.filter((r) => r.assignedEvaluatorId === evaluatorId)
    const { paged, meta } = paginate(mine, url)
    return envelope(paged, meta)
  }),

  http.get('/api/v1/bag/requests/:id', async ({ params }) => {
    await delay(300)
    if (maybeFail(0.06)) return errorResponse('Failed to load the bag request', 503)
    const r = requests.find((x) => x.id === params.id)
    if (!r) return errorResponse('Bag request not found', 404)
    return envelope(toDetail(r))
  }),

  http.post('/api/v1/bag/requests/:id/assign', async ({ request: req, params }) => {
    await delay(520)
    if (maybeFail(0.07)) return errorResponse('Could not assign the request', 500)
    const r = requests.find((x) => x.id === params.id)
    if (!r) return errorResponse('Bag request not found', 404)
    const body = await req.json().catch(() => ({}))
    const evaluatorId = Number(body.evaluatorId)
    if (!evaluatorId || !usersFixture.some((u) => u.id === evaluatorId && isEvaluator(u))) {
      return errorResponse('Choose an active evaluator', 422, {
        evaluatorId: ['Choose an active evaluator.'],
      })
    }
    r.assignedEvaluatorId = evaluatorId
    r.assignedBy = 'request_follower'
    r.assignedAt = new Date().toISOString()
    r.status = 'assigned'
    r.assignmentHistory.push({
      id: nextHistoryId(),
      evaluatorId,
      action: 'assigned',
      reason: null,
      at: r.assignedAt,
    })
    return envelope(toDetail(r))
  }),

  http.post('/api/v1/bag/requests/:id/reassign', async ({ request: req, params }) => {
    await delay(520)
    if (maybeFail(0.07)) return errorResponse('Could not reassign the request', 500)
    const r = requests.find((x) => x.id === params.id)
    if (!r) return errorResponse('Bag request not found', 404)
    if (r.status === 'completed' || r.status === 'cancelled') {
      return errorResponse('This request can no longer be reassigned', 422)
    }
    const body = await req.json().catch(() => ({}))
    const evaluatorId = Number(body.evaluatorId)
    const reason = (body.reason ?? '').trim()
    if (!evaluatorId || !usersFixture.some((u) => u.id === evaluatorId && isEvaluator(u))) {
      return errorResponse('Choose an active evaluator', 422, {
        evaluatorId: ['Choose an active evaluator.'],
      })
    }
    if (!reason) {
      return errorResponse('A reassignment reason is required', 422, {
        reason: ['A reassignment reason is required.'],
      })
    }
    r.assignedEvaluatorId = evaluatorId
    r.assignedAt = new Date().toISOString()
    if (r.status === 'awaiting-assignment') r.status = 'assigned'
    r.assignmentHistory.push({
      id: nextHistoryId(),
      evaluatorId,
      action: 'reassigned',
      reason,
      at: r.assignedAt,
    })
    return envelope(toDetail(r))
  }),
]
