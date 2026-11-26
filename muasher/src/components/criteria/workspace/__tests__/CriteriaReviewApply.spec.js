import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CriteriaReviewApply from '@/components/criteria/workspace/CriteriaReviewApply.vue'
import { criteriaService } from '@/services/criteriaService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: { publishVersion: vi.fn(), getMain: vi.fn(), checkVersion: vi.fn() },
}))

const draft = { id: 4, versionNumber: 2, status: 'draft' }
const published = { id: 1, versionNumber: 1, status: 'published' }
const preview = {
  preview: { value: { replaces: { versionNumber: 1 }, reboundOpenAssessments: [] } },
  loading: { value: false },
  failed: { value: false },
  load: vi.fn(),
}

function mountApply(props = {}, { superAdmin = false } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['criteria_officer'], { isSuperAdmin: superAdmin }))
  return mount(CriteriaReviewApply, {
    props: { version: draft, published, preview, ...props },
    global: globals,
  })
}

function inconsistent() {
  return Object.assign(new Error('rejected'), {
    normalized: {
      code: 'criteria.weights_inconsistent',
      message: 'The criteria cannot be published until the weights are consistent.',
      meta: {
        problems: [
          {
            nodeType: 'main',
            nodeId: 11,
            code: 'M1',
            problem: 'weights_mismatch',
            expectedWeight: '1000.00',
            actualWeight: '750.00',
          },
        ],
      },
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.checkVersion.mockResolvedValue({ data: {}, meta: {} })
  criteriaService.getMain.mockResolvedValue({
    data: { main: { id: 11, code: 'M1', name: { ar: 'القيادة', en: 'Leadership' } } },
  })
})

describe('CriteriaReviewApply', () => {
  it('requires a scope before publishing over a published version', async () => {
    const wrapper = mountApply()
    await wrapper.find('form').trigger('submit')
    expect(wrapper.find('.scope-choice .field__error').text()).toBe(
      'Choose where the change applies.',
    )
    expect(criteriaService.publishVersion).not.toHaveBeenCalled()
  })

  it('publishes with the chosen scope and reason and reports the outcome', async () => {
    criteriaService.publishVersion.mockResolvedValue({
      data: { id: 4, versionNumber: 2 },
      meta: { effect: { scope: 'reset_open' } },
    })
    const wrapper = mountApply()
    await wrapper.findAll('.scope-choice__input')[2].setValue(true)
    await wrapper
      .find('textarea[name="publishReason"]')
      .setValue('Annual review of the criteria weights.')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(criteriaService.publishVersion).toHaveBeenCalledWith(4, {
      scope: 'reset_open',
      reason: 'Annual review of the criteria weights.',
    })
    expect(wrapper.emitted('published')[0][0].meta.effect.scope).toBe('reset_open')
  })

  it('links each offending node to its place in the workspace when publishing is refused', async () => {
    criteriaService.publishVersion.mockRejectedValue(inconsistent())
    const wrapper = mountApply()
    await wrapper.findAll('.scope-choice__input')[0].setValue(true)
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const problem = wrapper.find('.criteria-problems__item')
    expect(problem.text()).toContain('The active sub-criteria of Leadership total 750.00')
    expect(problem.find('a').attributes('href')).toBe(
      '/admin/settings/criteria?version=4&node=main-11',
    )
    expect(wrapper.emitted('published')).toBeUndefined()
  })

  it('needs no scope for the very first publication', () => {
    const wrapper = mountApply({ published: null })
    expect(wrapper.find('.scope-choice').exists()).toBe(false)
    expect(wrapper.text()).toContain('first published version')
  })

  it('makes the reason mandatory for the Super Admin', async () => {
    const wrapper = mountApply({}, { superAdmin: true })
    await wrapper.findAll('.scope-choice__input')[1].setValue(true)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Enter the reason for this change.')
    expect(criteriaService.publishVersion).not.toHaveBeenCalled()
  })

  it('goes back to the previous step', async () => {
    const wrapper = mountApply()
    const back = wrapper.findAll('button').find((button) => button.text() === 'Back')
    await back.trigger('click')
    expect(wrapper.emitted('back')).toHaveLength(1)
  })
})
