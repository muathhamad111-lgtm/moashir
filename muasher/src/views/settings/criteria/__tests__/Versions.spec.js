import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Versions from '@/views/settings/criteria/Versions.vue'
import { criteriaService } from '@/services/criteriaService'
import { createTestGlobals } from '@/test-utils'
import { useTableColumnsStore } from '@/stores/tableColumns'
import {
  CORRECTION_2,
  DRAFT_3,
  PUBLICATION_2,
  VERSION_1,
  VERSION_2,
} from './criteriaVersionFixtures'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: { listVersions: vi.fn(), listEditEvents: vi.fn() },
}))

async function mountVersions(locale = 'en') {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(Versions, { global: globals })
  await flushPromises()
  return { wrapper, router: globals.router }
}

const rows = (wrapper) => wrapper.findAll('tbody tr')

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.listVersions.mockResolvedValue({ data: [VERSION_1, VERSION_2, DRAFT_3] })
  criteriaService.listEditEvents.mockResolvedValue({ data: [CORRECTION_2, PUBLICATION_2] })
})

describe('criteria Versions tab', () => {
  it('lists the versions newest first with their state', async () => {
    const { wrapper } = await mountVersions()
    expect(criteriaService.listEditEvents).toHaveBeenCalledWith({ perPage: 100, page: 1 })
    const names = rows(wrapper).map((row) => row.find('.criteria-versions__name').text())
    expect(names).toEqual(['Version 3', 'Version 2 — 2027 review', 'Version 1'])
    expect(rows(wrapper)[0].text()).toContain('Being edited')
    expect(rows(wrapper)[1].text()).toContain('Published')
  })

  it('shows how a version was published: scope, reason, publisher and open requests', async () => {
    const row = rows((await mountVersions()).wrapper)[1]
    expect(row.text()).toContain('Khalid Al-Omari')
    expect(row.text()).toContain('Reason: Annual review of the model')
    expect(row.text()).toContain('4 open requests')
  })

  it('links a version with changes to its "What changed" page and every version to the criteria', async () => {
    const { wrapper } = await mountVersions()
    const linksOf = (row) => row.findAll('a').map((link) => link.attributes('href'))
    expect(linksOf(rows(wrapper)[1])).toEqual([
      '/admin/settings/criteria/versions/3',
      '/admin/settings/criteria?version=3',
    ])
    expect(linksOf(rows(wrapper)[2])).toEqual(['/admin/settings/criteria?version=1'])
  })

  it('shows the linked requests and the score-affecting changes once the viewer chooses them', async () => {
    criteriaService.listVersions.mockResolvedValue({
      data: [VERSION_1, { ...VERSION_2, boundAssessments: 14, boundOpenAssessments: 4 }, DRAFT_3],
    })
    const { wrapper } = await mountVersions()
    useTableColumnsStore().choices = {
      'criteria-versions': ['published', 'linked', 'scoreAffecting'],
    }
    await flushPromises()

    const headers = wrapper.findAll('thead th').map((header) => header.text())
    const cell = (label) => rows(wrapper)[1].findAll('td')[headers.indexOf(label)].text()
    expect(cell('Linked requests')).toBe('14')
    expect(cell('Score-affecting changes')).toBe('2')
  })

  it('marks the versions tab as the current page', async () => {
    const { wrapper, router } = await mountVersions()
    await router.push('/admin/settings/criteria/versions')
    await flushPromises()
    expect(wrapper.find('.criteria-page-tabs .is-active').text()).toBe('Versions')
  })

  it('renders the Arabic texts', async () => {
    const { wrapper } = await mountVersions('ar')
    expect(rows(wrapper)[1].text()).toContain('منشور')
    expect(wrapper.find('.criteria-page-tabs').text()).toContain('الإصدارات')
  })
})
