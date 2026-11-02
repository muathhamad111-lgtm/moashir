import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { AWARD_REGIONS, seedAwardResults } from '../fixtures/awardResults'
import { failsRandomly } from '../contract/faults'

let results = seedAwardResults()
const approvedRegions = new Set()
let nationalApproved = false

export function resetAwardResults() {
  results = seedAwardResults()
  approvedRegions.clear()
  nationalApproved = false
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}
function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}
const maybeFail = failsRandomly(0.06)

function row(r) {
  return { ...r, entityName: { ...r.entityName } }
}

function regionsMeta() {
  return AWARD_REGIONS.map((region) => ({
    key: region.key,
    name: { ...region.name },
    approved: approvedRegions.has(region.key),
    rankedCount: results.filter((r) => r.region === region.key && r.regionalRank != null).length,
  }))
}

export const awardResultsHandlers = [
  http.get('/api/v1/award/results', async () => {
    await delay(360)
    if (maybeFail()) return errorResponse('Failed to load the award results', 503)
    const sorted = [...results].sort((a, b) => b.average - a.average)
    return envelope(sorted.map(row), { regions: regionsMeta() })
  }),

  http.post('/api/v1/award/results/:id/rank', async ({ request, params }) => {
    await delay(360)
    if (maybeFail(0.07)) return errorResponse('Could not set the rank', 500)
    const entry = results.find((r) => String(r.assessmentId) === String(params.id))
    if (!entry) return errorResponse('Result not found', 404)
    if (approvedRegions.has(entry.region)) {
      return errorResponse('This region has already been approved', 422)
    }
    const body = await request.json().catch(() => ({}))
    const rank = body.rank === null || body.rank === undefined ? null : Number(body.rank)
    if (rank !== null && ![1, 2, 3].includes(rank)) {
      return errorResponse('Rank must be 1, 2, or 3', 422, { rank: ['Rank must be 1, 2, or 3.'] })
    }
    if (rank !== null) {
      for (const r of results) {
        if (r.region === entry.region && r.assessmentId !== entry.assessmentId && r.regionalRank === rank) {
          r.regionalRank = null
        }
      }
    }
    entry.regionalRank = rank
    return envelope(row(entry))
  }),

  http.post('/api/v1/award/results/region/:region/approve', async ({ params }) => {
    await delay(460)
    if (maybeFail(0.07)) return errorResponse('Could not approve the region', 500)
    const region = params.region
    if (!AWARD_REGIONS.some((r) => r.key === region)) return errorResponse('Region not found', 404)
    const inRegion = results.filter((r) => r.region === region)
    const ranked = inRegion.filter((r) => r.regionalRank != null)
    if (!ranked.length) {
      return errorResponse('Select at least one winner before approving', 422, {
        winners: ['Select at least one winner before approving.'],
      })
    }
    for (const r of inRegion) {
      r.status = r.regionalRank != null ? 'regional-winner' : 'non-winner'
    }
    approvedRegions.add(region)
    return envelope(inRegion.map(row), { approved: true, region })
  }),

  http.get('/api/v1/award/national', async () => {
    await delay(360)
    if (maybeFail()) return errorResponse('Failed to load the national candidates', 503)
    const winners = results
      .filter((r) => r.status === 'regional-winner' || r.status === 'national-winner')
      .sort((a, b) => (a.regionalRank ?? 9) - (b.regionalRank ?? 9) || b.average - a.average)
    return envelope(winners.map(row), { approved: nationalApproved })
  }),

  http.post('/api/v1/award/national/:id/rank', async ({ request, params }) => {
    await delay(360)
    if (maybeFail(0.07)) return errorResponse('Could not set the national rank', 500)
    if (nationalApproved) return errorResponse('The national result has already been approved', 422)
    const entry = results.find((r) => String(r.assessmentId) === String(params.id))
    if (!entry || (entry.status !== 'regional-winner' && entry.status !== 'national-winner')) {
      return errorResponse('Candidate not found', 404)
    }
    const body = await request.json().catch(() => ({}))
    const rank = body.rank === null || body.rank === undefined ? null : Number(body.rank)
    if (rank !== null && ![1, 2, 3].includes(rank)) {
      return errorResponse('Rank must be 1, 2, or 3', 422, { rank: ['Rank must be 1, 2, or 3.'] })
    }
    if (rank !== null) {
      for (const r of results) {
        if (r.assessmentId !== entry.assessmentId && r.nationalRank === rank) r.nationalRank = null
      }
    }
    entry.nationalRank = rank
    return envelope(row(entry))
  }),

  http.post('/api/v1/award/national/approve', async () => {
    await delay(460)
    if (maybeFail(0.07)) return errorResponse('Could not approve the national result', 500)
    const ranked = results.filter((r) => r.nationalRank != null)
    if (!ranked.length) {
      return errorResponse('Select at least one national winner before approving', 422, {
        winners: ['Select at least one national winner before approving.'],
      })
    }
    for (const r of ranked) r.status = 'national-winner'
    nationalApproved = true
    return envelope(ranked.map(row), { approved: true })
  }),
]
