import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CommitteeWorkspace from '@/components/committee/CommitteeWorkspace.vue'
import { committeeWorkService } from '@/services/committeeWorkService'
import { createTestGlobals } from '@/test-utils'
import {
  PRIOR_SCORES,
  committeeEvaluationOf,
} from '@/components/committee/__tests__/committeeEvaluationFixture'

vi.mock('@/services/committeeWorkService', () => ({
  committeeWorkService: {
    getAssignment: vi.fn(),
    saveScores: vi.fn(),
    saveNotes: vi.fn(),
    submit: vi.fn(),
  },
}))

const mounted = []

async function mountWorkspace(evaluation, locale = 'ar') {
  committeeWorkService.getAssignment.mockResolvedValue({ data: evaluation })
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  const wrapper = mount(CommitteeWorkspace, {
    props: { assignmentId: 7 },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

function priorRowOf(wrapper, subId) {
  return wrapper.find(`#committee-sub-${subId} .committee-prior__score`)
}

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('CommitteeWorkspace scoring types', () => {
  it('reads a prior achievement score as achieved through the real panels of an unstamped request', async () => {
    const wrapper = await mountWorkspace(committeeEvaluationOf({ prior: PRIOR_SCORES }))
    const achievement = priorRowOf(wrapper, 103)
    expect(achievement.exists()).toBe(true)
    expect(achievement.text()).toContain('متحقق')
    expect(achievement.text()).not.toContain('غير متحقق')
    expect(achievement.text()).not.toContain('1.00')
    expect(priorRowOf(wrapper, 102).text()).toContain('7.50 — 75.00%')
  })

  it('gives the field and the prior scores the same typed unit', async () => {
    const wrapper = await mountWorkspace(committeeEvaluationOf({ prior: PRIOR_SCORES }), 'en')
    const panel = wrapper.find('#committee-sub-103')
    expect(panel.find('.achievement-score').exists()).toBe(true)
    expect(panel.find('input[inputmode="decimal"]').exists()).toBe(false)
    expect(priorRowOf(wrapper, 103).text()).toContain('Achieved')
    const scorePanel = wrapper.find('#committee-sub-102')
    expect(scorePanel.find('input[inputmode="decimal"]').exists()).toBe(true)
  })
})
