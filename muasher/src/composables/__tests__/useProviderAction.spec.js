import { describe, expect, it, vi } from 'vitest'
import { PROVIDER_ACTION_STATES, useProviderAction } from '@/composables/useProviderAction'

function failure(status, body = {}, headers = {}) {
  return { response: { status, headers }, normalized: { status, code: body.code ?? status, message: body.message ?? '', fieldErrors: body.errors ?? {} } }
}

describe('useProviderAction', () => {
  it('moves from pending to success and keeps the result', async () => {
    const settled = vi.fn()
    const action = useProviderAction(() => Promise.resolve({ data: { ok: true } }), { onSettled: settled })
    const pending = action.execute()
    expect(action.state.value).toBe(PROVIDER_ACTION_STATES.PENDING)
    await pending
    expect(action.state.value).toBe(PROVIDER_ACTION_STATES.SUCCESS)
    expect(action.result.value).toEqual({ ok: true })
    expect(settled).toHaveBeenCalledOnce()
  })

  it('maps a provider error to its kind and a rate limit to its wait time', async () => {
    const provider = useProviderAction(() => Promise.reject(failure(502, { code: 'provider_error', errors: { providerErrorKind: ['quota_exhausted'] } })))
    await provider.execute()
    expect(provider.failure.value).toMatchObject({ type: 'provider', kind: 'quota_exhausted' })

    const limited = useProviderAction(() => Promise.reject(failure(429, { code: 'rate_limited' }, { 'retry-after': '37' })))
    await limited.execute()
    expect(limited.failure.value).toMatchObject({ type: 'rate_limited', retryAfter: 37 })
  })

  it('reports forbidden and invalid input with the field message', async () => {
    const forbidden = useProviderAction(() => Promise.reject(failure(403)))
    await forbidden.execute()
    expect(forbidden.failure.value.type).toBe('forbidden')

    const invalid = useProviderAction(() => Promise.reject(failure(422, { errors: { recipient: ['The value is not valid.'] } })))
    await invalid.execute()
    expect(invalid.failure.value).toMatchObject({ type: 'invalid', fieldMessage: 'The value is not valid.' })
  })

  it('ignores a second run while one is pending', async () => {
    const run = vi.fn(() => new Promise((resolve) => setTimeout(() => resolve({ data: {} }), 5)))
    const action = useProviderAction(run)
    const first = action.execute()
    await action.execute()
    await first
    expect(run).toHaveBeenCalledOnce()
  })
})
