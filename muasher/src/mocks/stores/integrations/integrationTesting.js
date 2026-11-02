import { integrationReject } from './integrationRejection'

const WINDOW_MS = 60000
const LIMIT = 3

let attempts = new Map()

export function resetIntegrationTesting() {
  attempts = new Map()
}

export function throttleTest(user, key) {
  const bucket = `${user.id}:${key}`
  const now = Date.now()
  const recent = (attempts.get(bucket) ?? []).filter((time) => now - time < WINDOW_MS)
  if (recent.length >= LIMIT) {
    const seconds = Math.max(1, Math.ceil((WINDOW_MS - (now - recent[0])) / 1000))
    throw integrationReject.rateLimited(seconds)
  }
  attempts.set(bucket, [...recent, now])
}

export function testRecord(user, { ok, errorKind = null, latencyMs }) {
  return { at: new Date().toISOString(), ok, errorKind, latencyMs, by: user }
}

export function check(key, ok, detailCode = null) {
  return { key, ok, detailCode }
}

export function connectionResult({ provider, mode, latencyMs, checks, info = {}, errorKind = null }) {
  return { ok: errorKind === null, provider, mode, latencyMs, checks, info, errorKind }
}

export function settleTest(row, actor, result) {
  row.lastTest = testRecord(actor, { ok: result.ok, errorKind: result.errorKind, latencyMs: result.latencyMs })
  if (!result.ok) throw integrationReject.provider(result.errorKind, result)
  return result
}

export function failOnVerdict(verdict) {
  if (verdict && verdict !== 'ok') throw integrationReject.provider(verdict)
}

export function representativeTest(rows) {
  const active = rows.find((row) => row?.isActive)
  if (active) return active.lastTest ? { ...active.lastTest } : null
  const tested = rows.filter((row) => row?.lastTest).sort((left, right) => right.lastTest.at.localeCompare(left.lastTest.at))
  return tested[0] ? { ...tested[0].lastTest } : null
}
