import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { useBulkQualification } from '@/composables/useBulkQualification'
import { filteringService } from '@/services/filteringService'

vi.mock('@/services/filteringService', () => ({
  filteringService: { eligible: vi.fn(), qualifyEligible: vi.fn() },
}))

const PREVIEW = { count: 2, thresholdPercent: '60.00', ownAssessments: 0, requests: [] }

beforeEach(() => vi.clearAllMocks())

describe('useBulkQualification', () => {
  it('reads the preview for the cycle and reloads it after qualifying', async () => {
    filteringService.eligible.mockResolvedValue({ data: PREVIEW })
    filteringService.qualifyEligible.mockResolvedValue({
      data: { qualified: 2, qualifiedIds: [1, 2], skipped: [] },
    })
    const bulk = useBulkQualification(ref(31))
    await flushPromises()
    expect(bulk.preview.value.count).toBe(2)

    const outcome = await bulk.run({ reason: 'سبب' })
    expect(filteringService.qualifyEligible).toHaveBeenCalledWith(31, { reason: 'سبب' })
    expect(outcome.qualified).toBe(2)
    expect(filteringService.eligible).toHaveBeenCalledTimes(2)
    expect(bulk.busy.value).toBe(false)
  })

  it('hides the action when the preview is refused', async () => {
    filteringService.eligible.mockRejectedValue(Object.assign(new Error('no'), { status: 404 }))
    const bulk = useBulkQualification(ref(31))
    await flushPromises()
    expect(bulk.preview.value).toBeNull()
  })
})
