import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CriteriaVersionPanel from '@/components/criteria/CriteriaVersionPanel.vue'
import { criteriaService } from '@/services/criteriaService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: {
    createDraft: vi.fn(),
    checkVersion: vi.fn(),
    listMains: vi.fn(),
    discardDraft: vi.fn(),
  },
}))

const published = {
  id: 1,
  versionNumber: 1,
  status: 'published',
  publishedAt: '2026-10-31T09:00:00Z',
  scaleKind: 'of-10',
}
const draft = { id: 4, versionNumber: 2, status: 'draft' }

function mountPanel(roles, props) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(roles))
  return mount(CriteriaVersionPanel, { props, global: { ...globals, stubs: { teleport: true } } })
}

function buttonLabels(wrapper) {
  return wrapper.findAll('.criteria-version__actions button').map((button) => button.text())
}

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.listMains.mockResolvedValue({ data: [] })
})

describe('CriteriaVersionPanel', () => {
  it('shows the published banner with the scale and offers a draft copy to the Criteria Officer', () => {
    const wrapper = mountPanel(['criteria_officer'], { version: published, draft: null, published })
    expect(wrapper.find('.criteria-version--success').text()).toContain('Published version 1')
    expect(wrapper.text()).toContain('Assessment scale: Out of 10')
    expect(buttonLabels(wrapper)).toEqual([
      'Edit criteria (create a draft copy)',
      'Correct version name',
    ])
  })

  it('points to the open draft instead of offering a second one', () => {
    const wrapper = mountPanel(['criteria_officer'], { version: published, draft, published })
    expect(buttonLabels(wrapper)).toEqual(['Correct version name'])
    expect(wrapper.find('.criteria-version__draft-note a').attributes('href')).toBe(
      '/admin/settings/criteria?version=4',
    )
  })

  it('gives the System Administrator check and publish on a draft, nothing on the tree', () => {
    const wrapper = mountPanel(['system_admin'], { version: draft, draft, published })
    expect(buttonLabels(wrapper)).toEqual(['Check consistency', 'Publish'])
  })

  it('emits the new draft after "edit published"', async () => {
    criteriaService.createDraft.mockResolvedValue({ data: { id: 4, versionNumber: 2 } })
    const wrapper = mountPanel(['criteria_officer'], { version: published, draft: null, published })
    await wrapper.find('.criteria-version__actions button').trigger('click')
    await flushPromises()
    expect(criteriaService.createDraft).toHaveBeenCalledWith({})
    expect(wrapper.emitted('changed')[0]).toEqual([{ versionId: 4 }])
  })

  it('reports a consistent draft after the check', async () => {
    criteriaService.checkVersion.mockResolvedValue({ data: { consistent: true, problems: [] } })
    const wrapper = mountPanel(['criteria_officer'], { version: draft, draft, published })
    await wrapper.findAll('.criteria-version__actions button')[0].trigger('click')
    await flushPromises()
    expect(wrapper.find('.criteria-version-panel__consistent').text()).toContain('ready to publish')
  })
})
