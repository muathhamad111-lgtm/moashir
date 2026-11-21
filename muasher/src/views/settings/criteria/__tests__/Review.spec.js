import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import Review from '@/views/settings/criteria/Review.vue'
import { criteriaService } from '@/services/criteriaService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: {
    listVersions: vi.fn(),
    checkVersion: vi.fn(),
    previewPublication: vi.fn(),
    getTree: vi.fn(),
    getMain: vi.fn(),
    getSub: vi.fn(),
    publishVersion: vi.fn(),
  },
}))

const DRAFT = { id: 4, versionNumber: 2, status: 'draft' }
const PUBLISHED = { id: 1, versionNumber: 1, status: 'published' }
const PREVIEW = {
  replaces: { id: 1, versionNumber: 1 },
  changeCount: 1,
  scoreAffectingCount: 1,
  changes: [
    {
      nodeType: 'sub',
      code: 'M1.2',
      change: 'modified',
      fields: ['weight'],
      before: { weight: '400.00' },
      after: { weight: '350.00' },
      scoreAffecting: true,
      label: { ar: 'الحوكمة', en: 'Governance' },
    },
  ],
  reboundOpenAssessments: [
    { scope: 'all', count: 4 },
    { scope: 'new_only', count: 0 },
    { scope: 'reset_open', count: 4 },
  ],
}

async function mountReview({ versions = [DRAFT, PUBLISHED], roles = ['criteria_officer'] } = {}) {
  criteriaService.listVersions.mockResolvedValue({ data: versions })
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(roles))
  const wrapper = mount(Review, { global: globals })
  await flushPromises()
  return wrapper
}

const buttonNamed = (wrapper, label) =>
  wrapper.findAll('button').find((button) => button.text() === label)
const currentStep = (wrapper) => wrapper.find('[aria-current="step"]').text()

async function next(wrapper) {
  await buttonNamed(wrapper, 'Next').trigger('click')
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.checkVersion.mockResolvedValue({ data: { consistent: true, problems: [] } })
  criteriaService.previewPublication.mockResolvedValue({ data: PREVIEW })
  criteriaService.getTree.mockResolvedValue({
    data: { mains: [{ code: 'M1', name: { ar: 'القيادة', en: 'Leadership' } }] },
  })
  criteriaService.getMain.mockResolvedValue({
    data: { main: { id: 11, code: 'M1', name: { ar: 'القيادة', en: 'Leadership' } } },
  })
})

describe('criteria Review page', () => {
  it('walks notes, changes and apply when it replaces a published version', async () => {
    const wrapper = await mountReview()
    expect(wrapper.find('h1').text()).toBe('Review and publish version 2')
    expect(currentStep(wrapper)).toContain('Notes')
    expect(wrapper.find('.criteria-review__ready').exists()).toBe(true)

    await next(wrapper)
    expect(currentStep(wrapper)).toContain('Changes')
    expect(criteriaService.previewPublication).toHaveBeenCalledWith(4)
    expect(wrapper.find('.criteria-review-page__count').text()).toContain('1 change')
    expect(wrapper.find('.criteria-change-group__head').text()).toContain('Leadership')

    await next(wrapper)
    expect(currentStep(wrapper)).toContain('Apply and publish')
    const reach = wrapper.findAll('.scope-choice__reach').map((badge) => badge.text())
    expect(reach).toEqual([
      'Affects 4 open requests',
      'No open request affected',
      'Affects 4 open requests',
    ])
  })

  it('keeps Next disabled while something blocks publishing', async () => {
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
    const wrapper = await mountReview()
    expect(wrapper.find('.criteria-problems__item').text()).toContain('Leadership')
    expect(buttonNamed(wrapper, 'Next').attributes('disabled')).toBeDefined()
  })

  it('skips the changes step for the very first publication', async () => {
    const wrapper = await mountReview({ versions: [DRAFT] })
    expect(wrapper.findAll('.wizard__step')).toHaveLength(2)
    await next(wrapper)
    expect(currentStep(wrapper)).toContain('Apply and publish')
    expect(criteriaService.previewPublication).not.toHaveBeenCalled()
  })

  it('goes back from the stepper but never forward past the checks', async () => {
    const wrapper = await mountReview()
    await next(wrapper)
    const markers = wrapper.findAll('.wizard__button')
    await markers[2].trigger('click')
    expect(currentStep(wrapper)).toContain('Changes')
    await markers[0].trigger('click')
    expect(currentStep(wrapper)).toContain('Notes')
  })

  it('shows what was published instead of the steps', async () => {
    criteriaService.publishVersion.mockResolvedValue({
      data: { ...DRAFT, status: 'published' },
      meta: { affectedOpenCount: 4, effect: { scope: 'all' } },
    })
    const wrapper = await mountReview()
    await next(wrapper)
    await next(wrapper)
    await wrapper.find('input[value="all"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(criteriaService.publishVersion).toHaveBeenCalledWith(4, {
      scope: 'all',
      reason: null,
    })
    const done = wrapper.find('.criteria-publish-done')
    expect(done.text()).toContain('2')
    expect(done.text()).toContain('1 change published')
    expect(wrapper.find('.wizard').exists()).toBe(false)
  })

  it('explains when there is nothing to review', async () => {
    const wrapper = await mountReview({ versions: [PUBLISHED] })
    expect(wrapper.text()).toContain('Nothing to review')
    expect(criteriaService.checkVersion).not.toHaveBeenCalled()
  })

  it('does not offer publishing without the publish permission', async () => {
    const wrapper = await mountReview({ roles: [] })
    expect(wrapper.text()).toContain('Nothing to review')
  })
})
