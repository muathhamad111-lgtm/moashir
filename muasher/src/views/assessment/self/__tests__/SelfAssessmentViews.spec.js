import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import SelfForm from '@/views/assessment/self/Form.vue'
import SelfResult from '@/views/assessment/self/Result.vue'
import { assessmentsService } from '@/services/assessmentsService'
import { useAssessmentResultHandoffStore } from '@/stores/assessmentResultHandoff'
import { createTestGlobals } from '@/test-utils'
import { criteriaTree, formWith } from '@/utils/assessments/__tests__/fixtures'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: {
    list: vi.fn(),
    getPublishedCriteria: vi.fn(),
    startSelf: vi.fn(),
    getForm: vi.fn(),
    saveDraft: vi.fn(),
    submit: vi.fn(),
    getResult: vi.fn(),
  },
}))

const SAVED = { subs: { 102: { rawValue: '7.50', percent: '75.00' } }, points: { 1001: { rawValue: '8.00', percent: '80.00' } } }

async function settle() {
  for (let round = 0; round < 4; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 5))
    await flushPromises()
  }
}

async function mountView(component, { locale = 'en', path = '/assessment/self' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  await globals.router.push(path)
  const wrapper = mount(component, { global: { ...globals, stubs: { teleport: true } }, attachTo: document.body })
  await settle()
  return { wrapper, globals }
}

function rejection(status, code) {
  return Object.assign(new Error(code), { normalized: { status, code, meta: {}, message: code, fieldErrors: {} } })
}

beforeEach(() => {
  vi.clearAllMocks()
  assessmentsService.list.mockResolvedValue({ data: [], meta: {} })
  assessmentsService.getPublishedCriteria.mockResolvedValue({ data: criteriaTree })
})

describe('self assessment form view', () => {
  it('offers the start card with the published version, the scale label and the items count', async () => {
    const { wrapper } = await mountView(SelfForm)
    expect(wrapper.find('.self-start').exists()).toBe(true)
    expect(wrapper.find('.self-start__facts').text()).toContain('Criteria version 2')
    expect(wrapper.find('.self-start__facts').text()).toContain('Scale out of 10')
    expect(wrapper.find('.self-start__facts').text()).toContain('3 items to score')
  })

  it('shows the branded empty state when no criteria are published', async () => {
    assessmentsService.getPublishedCriteria.mockRejectedValue(rejection(404, 'criteria.no_published_version'))
    const { wrapper } = await mountView(SelfForm)
    expect(wrapper.find('.empty-state').text()).toContain('No published criteria yet')
  })

  it('loads the open draft with the server progress and the saved raw values', async () => {
    assessmentsService.list.mockResolvedValue({ data: [{ id: 9002 }], meta: {} })
    assessmentsService.getForm.mockResolvedValue({ data: { ...formWith({ scores: SAVED }), progress: { scored: 2, required: 3 } } })
    const { wrapper } = await mountView(SelfForm)
    expect(assessmentsService.getForm).toHaveBeenCalledWith(9002)
    expect(wrapper.find('.self-summary__number').text()).toContain('SLF-2026-000002')
    expect(wrapper.find('.self-summary__progress-value').text()).toBe('2 of 3 items scored')
    const inputs = wrapper.findAll('.raw-score input')
    expect(inputs.map((input) => input.element.value)).toEqual(['8.00', '', '7.50'])
  })

  it('keeps the form read-only for a frozen draft', async () => {
    assessmentsService.list.mockResolvedValue({ data: [{ id: 9002 }], meta: {} })
    const frozen = formWith({ scores: SAVED })
    frozen.assessment.isFrozen = true
    assessmentsService.getForm.mockResolvedValue({ data: frozen })
    const { wrapper } = await mountView(SelfForm, { locale: 'ar' })
    expect(wrapper.find('.assessment-notice--frozen').exists()).toBe(true)
    expect(wrapper.findAll('.raw-score input').every((input) => input.attributes('disabled') !== undefined)).toBe(true)
    expect(wrapper.findAll('.self-actions button').every((button) => button.attributes('disabled') !== undefined)).toBe(true)
  })

  it('blocks submission while the criteria update is in progress', async () => {
    assessmentsService.list.mockResolvedValue({ data: [{ id: 9002 }], meta: {} })
    const held = formWith({ scores: SAVED })
    held.assessment.criteriaUpdateInProgress = true
    assessmentsService.getForm.mockResolvedValue({ data: held })
    const { wrapper } = await mountView(SelfForm)
    const [save, submit] = wrapper.findAll('.self-actions button')
    expect(save.attributes('disabled')).toBeUndefined()
    expect(submit.attributes('disabled')).toBeDefined()
    expect(wrapper.find('.assessment-notice--warning').text()).toContain('Criteria update in progress')
  })

  it('submits a complete sheet after confirmation and hands the result to the result page', async () => {
    assessmentsService.list.mockResolvedValue({ data: [{ id: 9002 }], meta: {} })
    assessmentsService.getForm.mockResolvedValue({ data: formWith({ scores: { ...SAVED, points: { ...SAVED.points, 1002: { rawValue: '6.00', percent: '60.00' } } } }) })
    const result = { id: 9002, number: 'SLF-2026-000002', status: 'result-available', available: true, percent: '70.00' }
    assessmentsService.submit.mockResolvedValue({ data: result })
    const { wrapper, globals } = await mountView(SelfForm)
    await wrapper.findAll('.self-actions button')[1].trigger('click')
    await settle()
    const dialog = wrapper.find('.self-submit')
    expect(dialog.exists()).toBe(true)
    const buttons = wrapper.findAll('[role="dialog"] button')
    await buttons[buttons.length - 1].trigger('click')
    await settle()
    expect(assessmentsService.submit).toHaveBeenCalledWith(9002, { scores: { subs: { 102: '7.50' }, points: { 1001: '8.00', 1002: '6.00' } } })
    expect(globals.router.currentRoute.value.fullPath).toBe('/assessment/self/result/9002')
    expect(useAssessmentResultHandoffStore().result).toEqual(result)
  })
})

describe('self assessment result view', () => {
  it('shows the Q-94 placeholder with a way back to the draft', async () => {
    assessmentsService.getResult.mockResolvedValue({ data: { id: 9002, number: 'SLF-2026-000002', path: 'self', status: 'draft', isFrozen: false, available: false } })
    const { wrapper } = await mountView(SelfResult, { path: '/assessment/self/result/9002' })
    expect(wrapper.find('.result-pending').exists()).toBe(true)
    expect(wrapper.find('.result-pending .btn--primary').text()).toContain('Continue the draft')
  })

  it('shows the totals and the breakdown of an available result', async () => {
    assessmentsService.getResult.mockResolvedValue({
      data: { id: 9001, number: 'SLF-2026-000001', path: 'self', status: 'result-available', isFrozen: false, available: true, total: '6329.17', maxTotal: '8200.00', percent: '77.18', main: [], sub: [], points: [] },
    })
    const { wrapper } = await mountView(SelfResult, { path: '/assessment/self/result/9001', locale: 'ar' })
    expect(wrapper.find('.self-result__gauge-value').text()).toBe('77.18%')
    expect(wrapper.find('.self-result__score-total').text()).toContain('6329.17')
    expect(wrapper.find('.self-result__score-total').text()).toContain('8200.00')
  })

  it('shows the missing state on 404', async () => {
    assessmentsService.getResult.mockRejectedValue(rejection(404, 'not_found'))
    const { wrapper } = await mountView(SelfResult, { path: '/assessment/self/result/123' })
    expect(wrapper.find('.empty-state').text()).toContain('Assessment not found')
  })
})
