import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CriteriaReviewDialog from '@/components/criteria/workspace/CriteriaReviewDialog.vue'
import { criteriaService } from '@/services/criteriaService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: {
    checkVersion: vi.fn(),
    previewPublication: vi.fn(),
    getMain: vi.fn(),
    getSub: vi.fn(),
    publishVersion: vi.fn(),
  },
}))

const draft = { id: 4, versionNumber: 2, status: 'draft' }
const published = { id: 1, versionNumber: 1, status: 'published' }

async function openDialog(props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['criteria_officer']))
  const wrapper = mount(CriteriaReviewDialog, {
    props: { modelValue: false, version: draft, published, ...props },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await wrapper.setProps({ modelValue: true })
  await flushPromises()
  return wrapper
}

function buttonNamed(wrapper, label) {
  return wrapper.findAll('button').find((button) => button.text() === label)
}

function currentStep(wrapper) {
  return wrapper.find('.criteria-review__step.is-current').text()
}

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.checkVersion.mockResolvedValue({ data: { consistent: true, problems: [] } })
  criteriaService.previewPublication.mockResolvedValue({
    data: {
      replaces: { versionNumber: 1 },
      changeCount: 1,
      scoreAffectingCount: 1,
      changes: [],
      reboundOpenAssessments: [],
    },
  })
  criteriaService.getMain.mockResolvedValue({
    data: { main: { id: 11, code: 'M1', name: { ar: 'القيادة', en: 'Leadership' } } },
  })
})

describe('CriteriaReviewDialog', () => {
  it('walks notes, changes and apply when it replaces a published version', async () => {
    const wrapper = await openDialog()
    expect(currentStep(wrapper)).toContain('1. Notes')
    expect(wrapper.find('.criteria-review__ready').exists()).toBe(true)

    await buttonNamed(wrapper, 'Next').trigger('click')
    expect(currentStep(wrapper)).toContain('2. Changes')
    expect(criteriaService.previewPublication).toHaveBeenCalledWith(4)

    await buttonNamed(wrapper, 'Next').trigger('click')
    await flushPromises()
    expect(currentStep(wrapper)).toContain('3. Apply and publish')
    expect(wrapper.find('.scope-choice').exists()).toBe(true)
  })

  it('stops at the notes when something blocks publishing', async () => {
    criteriaService.checkVersion.mockResolvedValue({
      data: {
        consistent: false,
        problems: [
          {
            nodeType: 'main',
            nodeId: 11,
            problem: 'weights_mismatch',
            expectedWeight: '1000.00',
            actualWeight: '750.00',
          },
        ],
      },
    })
    const wrapper = await openDialog()
    expect(wrapper.find('.criteria-problems__item').text()).toContain('Leadership')
    expect(buttonNamed(wrapper, 'Next')).toBeUndefined()
  })

  it('skips the changes step for the very first publication', async () => {
    const wrapper = await openDialog({ published: null })
    expect(wrapper.findAll('.criteria-review__step')).toHaveLength(2)
    await buttonNamed(wrapper, 'Next').trigger('click')
    await flushPromises()
    expect(currentStep(wrapper)).toContain('2. Apply and publish')
    expect(criteriaService.previewPublication).not.toHaveBeenCalled()
  })
})
