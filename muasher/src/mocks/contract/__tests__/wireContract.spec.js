import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { HttpResponse } from 'msw'
import { handlers } from '@/mocks/handlers'
import { http } from '@/mocks/contract/http'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const probe = http.post('/api/v1/__contract-probe', async ({ request }) => {
  const url = new URL(request.url)
  const body = await request.json()
  return HttpResponse.json({
    data: {
      handlerBodyKeys: Object.keys(body),
      handlerNestedKeys: Object.keys(body.nestedValue ?? {}),
      handlerQueryKeys: [...url.searchParams.keys()],
      handlerStatusToken: url.searchParams.get('status'),
      echoedBody: body,
    },
    meta: {},
    errors: null,
  })
})

const server = setupServer(probe, ...handlers)

const CAMEL_CASE_KEY = /^[a-z][a-z0-9]*[A-Z]/

function camelKeysIn(value, path = '$', found = []) {
  if (Array.isArray(value)) {
    value.forEach((item, index) => camelKeysIn(item, `${path}[${index}]`, found))
  } else if (value && typeof value === 'object') {
    Object.entries(value).forEach(([key, child]) => {
      if (CAMEL_CASE_KEY.test(key)) found.push(`${path}.${key}`)
      if (key !== 'matrix' && key !== 'metadata') camelKeysIn(child, `${path}.${key}`, found)
    })
  }
  return found
}

const PARAMETERLESS_GET_PATHS = handlers
  .filter((handler) => handler.info.method === 'GET')
  .map((handler) => String(handler.info.path))
  .filter((path) => path.startsWith('/api/v1/') && !path.includes(':') && !path.includes('*'))

async function rawGet(path) {
  for (let attempt = 0; attempt < 12; attempt += 1) {
    const response = await wireFetch(`${window.location.origin}${path}`)
    if (response.status < 500) return response
  }
  return wireFetch(`${window.location.origin}${path}`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }))
beforeEach(() => seedMockSession({ id: 1, roles: ['admin'] }))
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('MSW mocks speak the snake_case API contract on the wire', () => {
  it('covers a broad set of GET endpoints', () => {
    expect(PARAMETERLESS_GET_PATHS.length).toBeGreaterThan(40)
  })

  it.each(PARAMETERLESS_GET_PATHS)('GET %s returns no camelCase keys', async (path) => {
    const response = await rawGet(path)
    if (!/json/.test(response.headers.get('content-type') ?? '')) return
    expect(camelKeysIn(await response.json())).toEqual([])
  })

  it('decodes snake_case bodies and query keys for the handler, and encodes the reply', async () => {
    const response = await wireFetch(
      `${MOCK_API_BASE}/__contract-probe?per_page=5&filter[recipient_role]=entity&status=under_review`,
      {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          official_email: 'info@example.sa',
          nested_value: { is_active: true },
        }),
      },
    )
    const body = await response.json()
    expect(body.data.handler_body_keys).toEqual(['officialEmail', 'nestedValue'])
    expect(body.data.handler_nested_keys).toEqual(['isActive'])
    expect(body.data.handler_query_keys).toEqual(['perPage', 'filter[recipientRole]', 'status'])
    expect(body.data.handler_status_token).toBe('under-review')
    expect(body.data.echoed_body).toEqual({
      official_email: 'info@example.sa',
      nested_value: { is_active: true },
    })
  })

  it('rejects a state-changing request without the XSRF header with 419', async () => {
    const response = await fetch(`${MOCK_API_BASE}/auth/logout`, { method: 'POST' })
    expect(response.status).toBe(419)
    expect((await response.json()).code).toBe('csrf.token_mismatch')
  })
})
