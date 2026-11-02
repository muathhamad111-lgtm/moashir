import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CriteriaPublishDialog from '@/components/criteria/CriteriaPublishDialog.vue'
import { criteriaService } from '@/services/criteriaService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: { publishVersion: vi.fn(), listMains: vi.fn() },
}))

const draft = { id: 4, versionNumber: 2, status: 'draft' }
const published = { id: 1, versionNumber: 1, status: 'published' }

function mountDialog(props = {}, { superAdmin = false } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['criteria_officer'], { isSuperAdmin: superAdmin }))
  return mount(CriteriaPublishDialog, {
    props: { modelValue: true, version: draft, published, ...props },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
}

function inconsistent() {
  return Object.assign(new Error('rejected'), {
    normalized: {
      code: 'criteria.weights_inconsistent',
      message: 'The criteria cannot be published until the weights are consistent.',
      fieldErrors: { 'nodes.main.11': ['M1 is off'] },
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
  criteriaService.listMains.mockResolvedValue({ data: [{ id: 11, code: 'M1' }] })
})

describe('CriteriaPublishDialog', () => {
  it('explains the three scopes with their effect and the protection of closed results', () => {
    const wrapper = mountDialog()
    const options = wrapper.findAll('.scope-choice__option')
    expect(options).toHaveLength(3)
    expect(options[0].text()).toContain('keep the scores of unchanged items')
    expect(options[1].text()).toContain('new requests')
    expect(options[2].text()).toContain('all their scores are cleared')
    expect(wrapper.find('.scope-choice__closed-note').text()).toContain(
      'Only text corrections reach them',
    )
  })

  it('requires a scope before publishing over a published version', async () => {
    const wrapper = mountDialog()
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
    const wrapper = mountDialog()
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
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([false])
  })

  it('lists the offending nodes with links to fix them when the weights are inconsistent', async () => {
    criteriaService.publishVersion.mockRejectedValue(inconsistent())
    const wrapper = mountDialog()
    await wrapper.findAll('.scope-choice__input')[0].setValue(true)
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const problem = wrapper.find('.criteria-problems__item')
    expect(problem.text()).toContain('M1')
    expect(problem.text()).toContain('750.00')
    expect(problem.find('a').attributes('href')).toBe('/admin/settings/criteria/11')
    expect(wrapper.emitted('published')).toBeUndefined()
  })

  it('needs no scope for the very first publication', () => {
    const wrapper = mountDialog({ published: null })
    expect(wrapper.find('.scope-choice').exists()).toBe(false)
    expect(wrapper.text()).toContain('first published version')
  })

  it('makes the reason mandatory for the Super Admin', async () => {
    const wrapper = mountDialog({}, { superAdmin: true })
    await wrapper.findAll('.scope-choice__input')[1].setValue(true)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Enter the reason for this change.')
    expect(criteriaService.publishVersion).not.toHaveBeenCalled()
  })
})
