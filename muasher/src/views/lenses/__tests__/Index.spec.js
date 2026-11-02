import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import LensesIndex from '@/views/lenses/Index.vue'
import { createTestGlobals } from '@/test-utils'

const status = ref('success')
const load = vi.fn()

const lensesSection = {
  id: 'sec-analytics-lenses',
  key: 'analytics-lenses',
  enabled: true,
  order: 7,
  title: { ar: 'العدسات التحليلية', en: 'Analytics lenses' },
  subtitle: {
    ar: 'تعتمد على آخر تقييم معتمد أو متقدم، وعند وجود تقييمين يتم اعتماد الأعلى، ولا تعتمد على التقييم الذاتي.',
    en: 'Based on the latest accredited or advanced assessment, the higher of the two, never on self assessment.',
  },
  description: { ar: 'لوحات تحليلية', en: 'Analytical dashboards' },
  items: [
    { id: 'lens-grantor', order: 1, icon: 'fa-solid fa-hand-holding-dollar', title: { ar: 'عدسة المانح', en: 'Grantor lens' }, description: { ar: 'وصف', en: 'Desc' } },
    { id: 'lens-supervisory', order: 2, icon: 'fa-solid fa-building-shield', title: { ar: 'عدسة الجهات الإشرافية', en: 'Supervisory lens' }, description: { ar: '', en: '' } },
    { id: 'lens-community', order: 3, icon: 'fa-solid fa-people-group', title: { ar: 'عدسة المجتمع', en: 'Community lens' }, description: { ar: '', en: '' } },
    { id: 'lens-vision', order: 4, icon: 'fa-solid fa-flag', title: { ar: 'عدسة برامج رؤية 2030', en: 'Vision 2030 lens' }, description: { ar: '', en: '' } },
    { id: 'lens-strategic', order: 5, icon: 'fa-solid fa-chess', title: { ar: 'العدسة الاستراتيجية', en: 'Strategic lens' }, description: { ar: '', en: '' } },
  ],
  meta: {},
}

const sectionByKey = vi.fn((key) => (key === 'analytics-lenses' ? lensesSection : null))

vi.mock('@/composables/useHomeContent', () => ({
  usePublicHome: () => ({
    status,
    load,
    localized: (value) => (typeof value === 'string' ? value : value?.en ?? ''),
    sectionByKey,
  }),
}))

function mountView() {
  return mount(LensesIndex, { global: createTestGlobals({ locale: 'en' }) })
}

describe('Lenses standalone page', () => {
  it('renders the 5 lenses from the mocked CMS section', () => {
    status.value = 'success'
    const wrapper = mountView()
    const cards = wrapper.findAll('.lens-card')
    expect(cards).toHaveLength(5)
    expect(cards[0].find('.lens-card__title').text()).toBe('Grantor lens')
    expect(wrapper.find('.lenses-page__title').text()).toBe('Analytics lenses')
  })

  it('shows the rule note from the section subtitle', () => {
    status.value = 'success'
    const wrapper = mountView()
    const rule = wrapper.find('.lenses-page__rule-text').text()
    expect(rule).toContain('never on self assessment')
  })

  it('shows an error state with retry when loading fails', async () => {
    status.value = 'error'
    const wrapper = mountView()
    expect(wrapper.findAll('.lens-card')).toHaveLength(0)
    await wrapper.find('button').trigger('click')
    expect(load).toHaveBeenCalled()
  })
})
