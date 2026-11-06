import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import LevelEmblem from '@/components/level/LevelEmblem.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import LevelRibbon from '@/components/level/LevelRibbon.vue'
import LevelHistoryList from '@/components/level/LevelHistoryList.vue'
import LevelThresholdTrack from '@/components/level/LevelThresholdTrack.vue'
import LevelScoreRing from '@/components/level/LevelScoreRing.vue'
import { CERTIFICATE_SEALS } from '@/constants/seals'

const RANGES = [
  { level: 'bronze', label: { ar: 'المستوى البرونزي', en: 'Bronze level' }, min: 60, max: 69.99 },
  { level: 'silver', label: { ar: 'المستوى الفضي', en: 'Silver level' }, min: 70, max: 79.99 },
]

function render(component, props, locale = 'ar') {
  return mount(component, { props, global: createTestGlobals({ locale }) })
}

describe('LevelEmblem', () => {
  it.each(['bronze', 'silver', 'gold', 'diamond'])(
    'uses the approved %s seal unmodified',
    (tier) => {
      const wrapper = render(LevelEmblem, { tier, size: 'xl' })
      expect(wrapper.find('img').attributes('src')).toBe(CERTIFICATE_SEALS[tier])
      expect(wrapper.classes()).toEqual(
        expect.arrayContaining(['level-emblem--seal', `level-tone--${tier}`, 'level-emblem--xl']),
      )
      expect(wrapper.attributes('aria-hidden')).toBe('true')
    },
  )

  it.each([
    ['none', 'flag'],
    ['progress', 'clock'],
    ['participation', 'certificate'],
  ])('draws a neutral placeholder for %s', (tier, icon) => {
    const wrapper = render(LevelEmblem, { tier })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.classes()).toContain('level-emblem--placeholder')
    expect(wrapper.classes()).toContain('level-tone--neutral')
    expect(wrapper.find('svg').attributes('data-icon')).toBeTruthy()
    expect(icon).toBeTruthy()
  })
})

describe('LevelBadge', () => {
  it('always carries the level name next to the seal', () => {
    const wrapper = render(LevelBadge, { tier: 'gold' })
    expect(wrapper.find('.level-badge__label').text()).toBe('ذهبي')
    expect(wrapper.find('.level-emblem--s').exists()).toBe(true)
    expect(wrapper.find('.level-badge__chip').exists()).toBe(false)
  })

  it('adds the re-assessment chip and reads in English', () => {
    const wrapper = render(LevelBadge, { tier: 'diamond', reassessment: true }, 'en')
    expect(wrapper.find('.level-badge__label').text()).toBe('Diamond')
    expect(wrapper.find('.level-badge__chip').text()).toBe('Re-assessment in progress')
  })
})

describe('LevelThresholdTrack', () => {
  it('draws one segment below the first threshold plus one per level and isolates the pin', () => {
    const wrapper = render(LevelThresholdTrack, { ranges: RANGES, percent: '74.50' })
    expect(wrapper.findAll('.level-track__segment')).toHaveLength(3)
    expect(wrapper.find('.level-track__caption').text()).toBe('جمعيتك 74.5%')
    expect(wrapper.find('.level-track__caption bdi').attributes('dir')).toBe('ltr')
  })

  it('mirrors the pin in RTL', () => {
    const ar = render(LevelThresholdTrack, { ranges: RANGES, percent: 80 })
    const en = render(LevelThresholdTrack, { ranges: RANGES, percent: 80 }, 'en')
    expect(ar.find('.level-track__pin').attributes('cx')).toBe('20%')
    expect(en.find('.level-track__pin').attributes('cx')).toBe('80%')
  })
})

describe('LevelRibbon and LevelHistoryList', () => {
  const current = {
    id: 'a',
    number: 'CRT-2026-000031',
    status: 'issued',
    percent: '82.00',
    issuedAt: '2026-10-30T08:00:00Z',
    tier: 'gold',
    isCurrent: true,
  }

  it('shows the entity level with the certificate number isolated', () => {
    const wrapper = render(LevelRibbon, { tier: 'gold', certificate: current })
    expect(wrapper.find('.level-ribbon__name').text()).toBe('المستوى الذهبي')
    expect(wrapper.find('.level-ribbon__meta bdi').text()).toBe('CRT-2026-000031')
    expect(wrapper.find('.level-ribbon__percent').text()).toBe('82%')
  })

  it('marks the current certificate and labels the others by status', () => {
    const wrapper = render(LevelHistoryList, {
      items: [
        current,
        {
          ...current,
          id: 'b',
          number: 'CRT-2025-000009',
          status: 'superseded',
          tier: 'silver',
          isCurrent: false,
        },
        {
          ...current,
          id: 'c',
          number: 'CRT-2024-000017',
          status: 'revoked',
          tier: 'participation',
          isCurrent: false,
        },
      ],
    })
    const chips = wrapper.findAll('.level-history__chip').map((chip) => chip.text())
    expect(chips).toEqual(['الحالية', 'سابقة', 'ملغاة'])
  })
})

describe('LevelScoreRing', () => {
  it('uses the same one-decimal score as the track and the ribbon', () => {
    const wrapper = render(LevelScoreRing, { value: '74.50', label: 'الدرجة المعتمدة' })
    expect(wrapper.find('.level-ring__number').text()).toBe('74.5')
    const whole = render(LevelScoreRing, { value: '82.00', label: 'الدرجة المعتمدة' })
    expect(whole.find('.level-ring__number').text()).toBe('82')
  })
})
