import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import LensesIndex from '@/views/lenses/Index.vue'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/contentService', () => ({ contentService: { getPage: vi.fn() } }))

const lensesPage = {
  key: 'lenses',
  title: { ar: 'العدسات التحليلية', en: 'Analytical lenses' },
  sections: [
    {
      id: 331,
      type: 'rich_text',
      title: { ar: 'العدسات التحليلية', en: 'Analytical lenses' },
      subtitle: { ar: 'قراءة النتائج', en: 'Reading the results from several angles' },
      description: {
        ar: 'أعلى نتيجة',
        en: 'The higher of the latest accredited and advanced results, never self assessment.',
      },
      items: [],
    },
    {
      id: 332,
      type: 'logos',
      title: { ar: 'العدسات', en: 'The lenses' },
      items: ['Donor', 'Supervisory', 'Community', 'Vision 2030', 'Strategic'].map(
        (name, index) => ({
          id: index + 1,
          icon: 'pie-chart',
          title: { ar: name, en: `${name} lens` },
          description: { ar: '', en: index === 0 ? 'Funding view' : '' },
        }),
      ),
    },
  ],
}

async function mountView() {
  const wrapper = mount(LensesIndex, { global: createTestGlobals({ locale: 'en' }) })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('Lenses standalone page (CMS page "lenses")', () => {
  it('renders the lens cards, the hero copy and the rule from the published page', async () => {
    contentService.getPage.mockResolvedValue({ data: lensesPage })
    const wrapper = await mountView()
    expect(contentService.getPage).toHaveBeenCalledWith('lenses')
    expect(wrapper.findAll('.lens-card')).toHaveLength(5)
    expect(wrapper.find('.lenses-page__title').text()).toBe('Analytical lenses')
    expect(wrapper.find('.lenses-page__subtitle').text()).toBe(
      'Reading the results from several angles',
    )
    expect(wrapper.find('.lenses-page__rule-text').text()).toContain('never self assessment')
  })

  it('falls back to the translated lenses while the page is not published', async () => {
    contentService.getPage.mockRejectedValue(
      Object.assign(new Error('not published'), { response: { status: 404 } }),
    )
    const wrapper = await mountView()
    expect(wrapper.findAll('.lens-card')).toHaveLength(5)
    expect(wrapper.find('.error-state').exists()).toBe(false)
  })

  it('shows an error state with retry when loading fails', async () => {
    contentService.getPage.mockRejectedValueOnce(
      Object.assign(new Error('down'), { response: { status: 503 } }),
    )
    const wrapper = await mountView()
    expect(wrapper.find('.error-state').exists()).toBe(true)
    contentService.getPage.mockResolvedValue({ data: lensesPage })
    await wrapper.find('.error-state button').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.lens-card')).toHaveLength(5)
  })
})
