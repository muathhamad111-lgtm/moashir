import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { useCriteriaProblemNodes } from '@/composables/useCriteriaProblemNodes'
import { criteriaService } from '@/services/criteriaService'

const { LEADERSHIP, PLANNING } = vi.hoisted(() => ({
  LEADERSHIP: { id: 21, code: 'M1', name: { ar: 'القيادة', en: 'Leadership' } },
  PLANNING: { id: 211, code: 'M2.1', mainCriterionId: 22, name: { ar: 'التخطيط', en: 'Planning' } },
}))

vi.mock('@/services/criteriaService', () => ({
  criteriaService: {
    getMain: vi.fn().mockResolvedValue({ data: { main: LEADERSHIP } }),
    getSub: vi.fn().mockResolvedValue({ data: { sub: PLANNING } }),
  },
}))

describe('useCriteriaProblemNodes', () => {
  it('loads each offending node once and links each problem to the screen that fixes it', async () => {
    const mainProblem = { nodeType: 'main', nodeId: 21, code: 'M1' }
    const subProblem = { nodeType: 'sub', nodeId: 211, code: 'M2.1' }
    const versionProblem = { nodeType: 'main', nodeId: null, code: null }
    const problems = ref([mainProblem, subProblem, { ...subProblem }, versionProblem])
    const { nodeOf, linkFor } = useCriteriaProblemNodes(ref(4), problems)
    await flushPromises()
    expect(criteriaService.getMain).toHaveBeenCalledTimes(1)
    expect(criteriaService.getMain).toHaveBeenCalledWith(21)
    expect(criteriaService.getSub).toHaveBeenCalledTimes(1)
    expect(criteriaService.getSub).toHaveBeenCalledWith(211)
    expect(nodeOf(mainProblem)).toEqual(LEADERSHIP)
    expect(nodeOf(subProblem)).toEqual(PLANNING)
    expect(nodeOf(versionProblem)).toBeNull()
    expect(linkFor(mainProblem)).toEqual({ name: 'admin.criteria.main', params: { mainId: 21 } })
    expect(linkFor(subProblem)).toEqual({
      name: 'admin.criteria.sub',
      params: { mainId: 22, subId: 211 },
    })
    expect(linkFor(versionProblem)).toEqual({ name: 'admin.criteria', query: { version: 4 } })
  })

  it('falls back to the version screen while a sub-criterion is unknown', () => {
    const { linkFor } = useCriteriaProblemNodes(ref(4), ref([]))
    expect(linkFor({ nodeType: 'sub', nodeId: 999, code: 'M9.9' })).toEqual({
      name: 'admin.criteria',
      query: { version: 4 },
    })
  })
})
