import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { awardResultsHandlers, resetAwardResults } from '@/mocks/handlers/awardResults'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...awardResultsHandlers)
const BASE = MOCK_API_BASE

async function jsonGet(path) {
  const res = await mockFetch(`${BASE}${path}`)
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function jsonPost(path, body) {
  const res = await mockFetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(body ?? {}),
  })
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function retryGet(path, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonGet(path)
    if (r.ok) return r
  }
  throw new Error(`get failed: ${path}`)
}
async function retryPost(path, body, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonPost(path, body)
    if (r.ok) return r
  }
  throw new Error(`post failed: ${path}`)
}
async function postResolved(path, body, tries = 20) {
  let res
  for (let i = 0; i < tries; i += 1) {
    res = await jsonPost(path, body)
    if (res.status !== 500 && res.status !== 503) return res
  }
  return res
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  server.resetHandlers()
  resetAwardResults()
})
afterAll(() => server.close())

describe('award results — regional ranking', () => {
  it('lists approved entities sorted by average, with region metadata', async () => {
    const { body } = await retryGet('/award/results')
    expect(body.data.length).toBeGreaterThan(0)
    for (let i = 1; i < body.data.length; i += 1) {
      expect(body.data[i - 1].average).toBeGreaterThanOrEqual(body.data[i].average)
    }
    expect(body.meta.regions.length).toBe(4)
    expect(body.meta.regions[0]).toHaveProperty('approved', false)
  })

  it('assigns a regional rank and keeps ranks unique within a region (swaps)', async () => {
    await retryPost('/award/results/9401/rank', { rank: 1 })
    await retryPost('/award/results/9411/rank', { rank: 1 })
    const { body } = await retryGet('/award/results')
    const byId = Object.fromEntries(body.data.map((r) => [r.assessmentId, r]))
    expect(byId[9411].regionalRank).toBe(1)
    expect(byId[9401].regionalRank).toBeNull()
  })

  it('rejects an invalid rank', async () => {
    const res = await postResolved('/award/results/9401/rank', { rank: 5 })
    expect(res.status).toBe(422)
  })

  it('refuses to approve a region with no ranked winners', async () => {
    const res = await postResolved('/award/results/region/makkah/approve', {})
    expect(res.status).toBe(422)
  })

  it('approves a region: ranked → regional-winner, the rest → non-winner', async () => {
    await retryPost('/award/results/9401/rank', { rank: 1 })
    await retryPost('/award/results/9411/rank', { rank: 2 })
    const approved = await retryPost('/award/results/region/riyadh/approve', {})
    const byId = Object.fromEntries(approved.body.data.map((r) => [r.assessmentId, r]))
    expect(byId[9401].status).toBe('regional-winner')
    expect(byId[9411].status).toBe('regional-winner')
    expect(byId[9412].status).toBe('non-winner')
    const locked = await postResolved('/award/results/9412/rank', { rank: 3 })
    expect(locked.status).toBe(422)
  })
})

describe('award results — national ranking', () => {
  async function approveRiyadhWinners() {
    await retryPost('/award/results/9401/rank', { rank: 1 })
    await retryPost('/award/results/9411/rank', { rank: 2 })
    await retryPost('/award/results/region/riyadh/approve', {})
  }

  it('gathers the regional winners as national candidates', async () => {
    await approveRiyadhWinners()
    const { body } = await retryGet('/award/national')
    const ids = body.data.map((r) => r.assessmentId)
    expect(ids).toContain(9401)
    expect(ids).toContain(9411)
    expect(
      body.data.every((r) => r.status === 'regional-winner' || r.status === 'national-winner'),
    ).toBe(true)
  })

  it('ranks + approves national winners', async () => {
    await approveRiyadhWinners()
    await retryPost('/award/national/9401/rank', { rank: 1 })
    const approved = await retryPost('/award/national/approve', {})
    expect(
      approved.body.data.some((r) => r.assessmentId === 9401 && r.status === 'national-winner'),
    ).toBe(true)
    const locked = await postResolved('/award/national/9411/rank', { rank: 2 })
    expect(locked.status).toBe(422)
  })

  it('refuses to approve the national result without a ranked winner', async () => {
    await approveRiyadhWinners()
    const res = await postResolved('/award/national/approve', {})
    expect(res.status).toBe(422)
  })
})
