import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeLevels from '@/components/public/HomeLevels.vue'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/contentService', () => ({
  contentService: { getCertificateLevels: vi.fn() },
}))

function localized(content) {
  return typeof content === 'string' ? content : (content?.ar ?? '')
}

const LEVELS = {
  data: {
    levels: [
      { level: 'bronze', label: { ar: 'المستوى البرونزي' }, minPercent: '60.00' },
      { level: 'silver', label: { ar: 'المستوى الفضي' }, minPercent: '70.00' },
      { level: 'gold', label: { ar: 'المستوى الذهبي' }, minPercent: '80.00' },
      { level: 'diamond', label: { ar: 'المستوى الماسي' }, minPercent: '90.00' },
    ],
    participationBelow: '60.00',
  },
}

function mountLevels() {
  return mount(HomeLevels, { props: { localized }, global: createTestGlobals() })
}

beforeEach(() => vi.clearAllMocks())

describe('HomeLevels', () => {
  it('shows the four seals with the thresholds read from the settings', async () => {
    contentService.getCertificateLevels.mockResolvedValue(LEVELS)
    const wrapper = mountLevels()
    await flushPromises()
    const cards = wrapper.findAll('.home-level')
    expect(cards.map((card) => card.find('.home-level__name').text())).toEqual([
      'المستوى البرونزي',
      'المستوى الفضي',
      'المستوى الذهبي',
      'المستوى الماسي',
    ])
    expect(cards[0].find('.level-range__value').text()).toBe('60–69.99%')
    expect(cards[0].find('.level-range__value').attributes('dir')).toBe('ltr')
    expect(cards[3].find('.home-level__range').text()).toBe('90% فأكثر')
    expect(cards[2].find('img').attributes('alt')).toBe('')
    expect(wrapper.find('.home-levels__verification').text()).toContain('رمز QR')
  })

  it('offers a retry when the thresholds cannot be read', async () => {
    contentService.getCertificateLevels.mockRejectedValueOnce(new Error('offline'))
    const wrapper = mountLevels()
    await flushPromises()
    expect(wrapper.find('.home-level').exists()).toBe(false)
    contentService.getCertificateLevels.mockResolvedValue(LEVELS)
    await wrapper.find('.error-state button').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.home-level')).toHaveLength(4)
  })
})
