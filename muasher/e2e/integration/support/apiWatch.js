const API_PATH = /\/(api\/v1|sanctum)\//
const BACKEND_HEADER = 'content-language'

export const SOURCES = { BACKEND: 'backend', MOCK: 'mock' }

function sourceOf(response) {
  return response.headers()[BACKEND_HEADER] ? SOURCES.BACKEND : SOURCES.MOCK
}

const QUIET_MS = 400
const POLL_MS = 100
const IDLE_WAIT_MS = 15000

function isApi(url) {
  return API_PATH.test(new URL(url).pathname)
}

export function watchApi(page) {
  const failures = []
  const calls = []
  const state = { inflight: 0, lastActivity: Date.now() }
  const settle = () => {
    state.inflight = Math.max(0, state.inflight - 1)
    state.lastActivity = Date.now()
  }
  page.on('request', (request) => {
    if (!isApi(request.url())) return
    state.inflight += 1
    state.lastActivity = Date.now()
  })
  page.on('requestfinished', (request) => isApi(request.url()) && settle())
  page.on('requestfailed', (request) => isApi(request.url()) && settle())
  page.on('response', async (response) => {
    const url = new URL(response.url())
    if (!API_PATH.test(url.pathname)) return
    const entry = {
      method: response.request().method(),
      path: url.pathname,
      status: response.status(),
      source: sourceOf(response),
    }
    calls.push(entry)
    if (response.status() < 400) return
    const body = await response.json().catch(() => ({}))
    failures.push({
      method: entry.method,
      path: entry.path,
      status: entry.status,
      code: body?.code ?? null,
    })
  })
  return {
    calls,
    async idle() {
      const deadline = Date.now() + IDLE_WAIT_MS
      while (Date.now() < deadline) {
        if (state.inflight === 0 && Date.now() - state.lastActivity >= QUIET_MS) return
        await new Promise((resolve) => setTimeout(resolve, POLL_MS))
      }
      throw new Error('The API calls of the page did not settle in time.')
    },
    sourcesOf(path) {
      return [...new Set(calls.filter((call) => path.test(call.path)).map((call) => call.source))]
    },
    failures,
    unexpected(allowed = []) {
      return failures.filter(
        (failure) =>
          !allowed.some((rule) => rule.status === failure.status && rule.path.test(failure.path)),
      )
    },
  }
}

export const GUEST_SESSION_PROBE = { status: 401, path: /^\/api\/v1\/auth\/session$/ }

export const PAGE_NOT_PUBLISHED = { status: 404, path: /^\/api\/v1\/content\/pages\/[a-z_]+$/ }

export const FOOTER_LEGAL_PROBES = [
  { status: 404, path: /^\/api\/v1\/content\/legal\/(terms|privacy)$/ },
  { status: 404, path: /^\/api\/v1\/content\/pages\/accessibility$/ },
]
