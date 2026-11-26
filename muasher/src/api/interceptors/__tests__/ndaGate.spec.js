import { afterEach, describe, expect, it, vi } from 'vitest'
import { holdForNdaAcceptance, isNdaRequired } from '@/api/interceptors/ndaGate'
import { markNdaAccepted, onNdaRequired, resetNdaGate } from '@/api/ndaEvents'

function refusal(method, code = 'nda.acceptance_required', status = 403) {
  const error = new Error('refused')
  error.config = { method, url: '/users' }
  error.response = { status, data: { code } }
  return error
}

afterEach(() => {
  resetNdaGate()
})

describe('agreement gate interceptor', () => {
  it('recognises only the agreement refusal', () => {
    expect(isNdaRequired(refusal('get'))).toBe(true)
    expect(isNdaRequired(refusal('get', 'forbidden'))).toBe(false)
    expect(isNdaRequired(refusal('get', 'nda.acceptance_required', 422))).toBe(false)
  })

  it('opens the modal once and replays a refused read after the user accepts', async () => {
    const client = { request: vi.fn().mockResolvedValue({ data: 'replayed' }) }
    const opened = vi.fn()
    const stop = onNdaRequired(opened)
    const handler = holdForNdaAcceptance(client)

    const first = handler(refusal('get'))
    const second = handler(refusal('get'))
    await Promise.resolve()
    expect(opened).toHaveBeenCalledTimes(1)
    expect(client.request).not.toHaveBeenCalled()

    markNdaAccepted()
    await expect(first).resolves.toEqual({ data: 'replayed' })
    await expect(second).resolves.toEqual({ data: 'replayed' })
    expect(client.request).toHaveBeenCalledWith(
      expect.objectContaining({ method: 'get', ndaReplayed: true }),
    )
    stop()
  })

  it('never resends a refused write: it opens the modal and lets the user repeat the action', async () => {
    const client = { request: vi.fn() }
    const opened = vi.fn()
    const stop = onNdaRequired(opened)

    for (const method of ['post', 'put', 'patch', 'delete']) {
      resetNdaGate()
      const error = refusal(method)
      await expect(holdForNdaAcceptance(client)(error)).rejects.toBe(error)
      expect(error.ndaGateHandled).toBe(true)
    }
    markNdaAccepted()
    await Promise.resolve()
    expect(client.request).not.toHaveBeenCalled()
    expect(opened).toHaveBeenCalledTimes(4)
    stop()
  })

  it('passes other errors through untouched', async () => {
    const client = { request: vi.fn() }
    const error = refusal('get', 'forbidden')

    await expect(holdForNdaAcceptance(client)(error)).rejects.toBe(error)
    expect(client.request).not.toHaveBeenCalled()
  })
})
