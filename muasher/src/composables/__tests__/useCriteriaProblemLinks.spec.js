import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { useCriteriaProblemLinks } from '@/composables/useCriteriaProblemLinks'
import { criteriaService } from '@/services/criteriaService'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: {
    listMains: vi.fn().mockResolvedValue({
      data: [
        { id: 21, code: 'M1' },
        { id: 22, code: 'M2' },
      ],
    }),
  },
}))

describe('useCriteriaProblemLinks', () => {
  it('links each problem to the screen that fixes it', async () => {
    const problems = ref([{ nodeType: 'sub', nodeId: 211, code: 'M2.1' }])
    const { linkFor } = useCriteriaProblemLinks(ref(4), problems)
    await flushPromises()
    expect(criteriaService.listMains).toHaveBeenCalledWith({ version: 4, perPage: 100 })
    expect(linkFor({ nodeType: 'main', nodeId: 21, code: 'M1' })).toEqual({
      name: 'admin.criteria.main',
      params: { mainId: 21 },
    })
    expect(linkFor(problems.value[0])).toEqual({
      name: 'admin.criteria.sub',
      params: { mainId: 22, subId: 211 },
    })
    expect(linkFor({ nodeType: 'main', nodeId: null, code: null })).toEqual({
      name: 'admin.criteria',
      query: { version: 4 },
    })
  })
})
