import { describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { useInsightsTool } from '@/composables/useInsightsTool'

function failure(status, code = null, meta = {}) {
  return Object.assign(new Error('failed'), { normalized: { status, code, meta } })
}

describe('useInsightsTool', () => {
  it('stays idle without a result and loads when one is selected', async () => {
    const loader = vi.fn().mockResolvedValue({ data: { counts: {} } })
    const id = ref(null)
    const tool = useInsightsTool(loader, id)
    expect(tool.status.value).toBe('idle')
    id.value = 9102
    await nextTick()
    await flushPromises()
    expect(loader).toHaveBeenCalledWith(9102)
    expect(tool.status.value).toBe('ready')
  })

  it('keeps the ineligibility reason of a 409', async () => {
    const loader = vi
      .fn()
      .mockRejectedValue(
        failure(409, 'insights.result_not_eligible', { reason: 'certificate_revoked' }),
      )
    const tool = useInsightsTool(loader, ref(9103))
    await flushPromises()
    expect(tool.status.value).toBe('ineligible')
    expect(tool.reason.value).toBe('certificate_revoked')
  })

  it('tells a hidden result from a failure', async () => {
    const missing = useInsightsTool(vi.fn().mockRejectedValue(failure(404)), ref(1))
    const broken = useInsightsTool(vi.fn().mockRejectedValue(failure(500)), ref(2))
    await flushPromises()
    expect(missing.status.value).toBe('missing')
    expect(broken.status.value).toBe('error')
  })
})
