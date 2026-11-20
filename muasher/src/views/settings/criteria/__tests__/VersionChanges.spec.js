import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import VersionChanges from '@/views/settings/criteria/VersionChanges.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { criteriaService } from '@/services/criteriaService'
import { createTestGlobals } from '@/test-utils'
import {
  CORRECTION_2,
  DRAFT_3,
  PUBLICATION_2,
  VERSION_1,
  VERSION_2,
} from './criteriaVersionFixtures'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: { listVersions: vi.fn(), listEditEvents: vi.fn(), getTree: vi.fn() },
}))

async function mountChanges(versionId = 3, locale = 'en') {
  const wrapper = mount(VersionChanges, {
    props: { versionId },
    global: createTestGlobals({ locale }),
  })
  await flushPromises()
  return wrapper
}

const codes = (wrapper) =>
  wrapper.findAll('.criteria-change .criteria-code').map((code) => code.text())
const tile = (wrapper, label) =>
  wrapper.findAll('.criteria-change-tile').find((button) => button.text().includes(label))

beforeEach(() => {
  vi.clearAllMocks()
  criteriaService.listVersions.mockResolvedValue({ data: [VERSION_1, VERSION_2, DRAFT_3] })
  criteriaService.listEditEvents.mockResolvedValue({ data: [CORRECTION_2, PUBLICATION_2] })
  criteriaService.getTree.mockResolvedValue({
    data: {
      mains: [
        { code: 'M1', name: { ar: 'القيادة', en: 'Leadership' } },
        { code: 'M2', name: { ar: 'النتائج', en: 'Results' } },
      ],
    },
  })
})

describe('criteria VersionChanges page', () => {
  it('states from which version, when, how and by whom the version was published', async () => {
    const wrapper = await mountChanges()
    expect(criteriaService.getTree).toHaveBeenCalledWith(3)
    expect(wrapper.find('h1').text()).toBe('What changed in version 2')
    const facts = wrapper.find('.criteria-page__subtitle').text()
    expect(facts).toContain('From Version 1 to Version 2')
    expect(facts).toContain('Apply to all assessments')
    expect(facts).toContain('Published by Khalid Al-Omari')
    expect(wrapper.text()).toContain('Reason: Annual review of the model')
  })

  it('counts the changes in three tiles and lists them under their main criteria', async () => {
    const wrapper = await mountChanges()
    expect(tile(wrapper, 'Keeps its score').text()).toContain('1')
    expect(tile(wrapper, 'Needs a new score').text()).toContain('1')
    expect(tile(wrapper, 'New items').text()).toContain('1')
    const heads = wrapper.findAll('.criteria-change-group__title').map((head) => head.text())
    expect(heads).toEqual(['Leadership', 'Results'])
    expect(codes(wrapper)).toEqual(['M1.1', 'M1.2', 'M2.4'])
  })

  it('filters by a pressed tile and by the search, and says when nothing matches', async () => {
    const wrapper = await mountChanges()
    await tile(wrapper, 'New items').trigger('click')
    expect(tile(wrapper, 'New items').attributes('aria-pressed')).toBe('true')
    expect(codes(wrapper)).toEqual(['M2.4'])
    await tile(wrapper, 'New items').trigger('click')
    await wrapper.find('input[name="changeSearch"]').setValue('governance')
    expect(codes(wrapper)).toEqual(['M1.2'])
    await wrapper.find('input[name="changeSearch"]').setValue('nothing like this')
    expect(wrapper.text()).toContain('No item matches the filter or the search.')
  })

  it('lists the text corrections made after publishing', async () => {
    const wrapper = await mountChanges()
    const corrections = wrapper.find('.criteria-version-corrections')
    expect(corrections.text()).toContain('Text corrections after publishing')
    expect(corrections.text()).toContain('1 text correction')
  })

  it('explains that the first version has nothing to compare', async () => {
    const wrapper = await mountChanges(1)
    expect(wrapper.find('.criteria-change-filters').exists()).toBe(false)
    expect(wrapper.text()).toContain('Nothing to compare')
  })

  it('says when the version does not exist', async () => {
    const wrapper = await mountChanges(99)
    expect(wrapper.text()).toContain('Version not found')
  })

  it('renders the Arabic texts', async () => {
    const wrapper = await mountChanges(3, 'ar')
    expect(wrapper.find('h1').text()).toBe('ما الذي تغيّر في الإصدار 2')
    expect(tile(wrapper, 'بنود جديدة').exists()).toBe(true)
  })

  describe('more than one page of edit events', () => {
    const filler = Array.from({ length: 100 }, (_, index) => ({
      ...CORRECTION_2,
      id: 100 + index,
      fromVersion: { id: 1, versionNumber: 1, label: null },
    }))
    const page = (data, number) => ({
      data,
      meta: { pagination: { page: number, perPage: 100, total: 101, lastPage: 2 } },
    })

    it('reads every page, so a publication on a later page is not cut off', async () => {
      criteriaService.listEditEvents
        .mockResolvedValueOnce(page(filler, 1))
        .mockResolvedValueOnce(page([PUBLICATION_2], 2))
      const wrapper = await mountChanges()
      expect(criteriaService.listEditEvents.mock.calls.map(([params]) => params)).toEqual([
        { perPage: 100, page: 1 },
        { perPage: 100, page: 2 },
      ])
      expect(codes(wrapper)).toEqual(['M1.1', 'M1.2', 'M2.4'])
    })

    it('shows an error instead of a partial list when a later page fails', async () => {
      criteriaService.listEditEvents
        .mockResolvedValueOnce(page(filler, 1))
        .mockRejectedValueOnce(new Error('network'))
      const wrapper = await mountChanges()
      expect(wrapper.find('.criteria-change-filters').exists()).toBe(false)
      expect(wrapper.find('.criteria-change').exists()).toBe(false)
      expect(wrapper.findComponent(ErrorState).exists()).toBe(true)
    })
  })
})
