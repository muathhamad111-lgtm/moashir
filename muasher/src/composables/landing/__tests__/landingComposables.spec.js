import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { servicePricesService } from '@/services/servicePricesService'
import { useLandingText } from '../useLandingText'
import { useLevelDial } from '../useLevelDial'
import { useKitStory } from '../useKitStory'
import { useLensCycle } from '../useLensCycle'
import { provideLandingReady, useLandingReady } from '../useLandingReady'
import { useLandingPrices } from '../useLandingPrices'
import { landingTiers } from '@/utils/landing/tiers'
import { logger } from '@/utils/logger'

vi.mock('@/utils/logger', () => ({
  logger: { error: vi.fn(), breadcrumb: vi.fn(), info: vi.fn() },
}))
vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))

const RANGES = [
  { level: 'bronze', min: 60 },
  { level: 'silver', min: 70 },
  { level: 'gold', min: 80 },
  { level: 'diamond', min: 90 },
]

class Observer {
  constructor(callback) {
    this.callback = callback
    Observer.last = this
  }

  observe() {}

  disconnect() {}
}

function withSetup(setup, { locale = 'ar' } = {}) {
  let result
  const Probe = defineComponent({
    setup() {
      result = setup()
      return () => h('div')
    },
  })
  const wrapper = mount(Probe, { global: createTestGlobals({ locale }) })
  return { result, wrapper }
}

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('useLandingText', () => {
  it('resolves localized fields, meta texts and items by role', () => {
    const section = {
      title: { ar: 'العنوان', en: 'Title' },
      description: { ar: 'الوصف', en: 'Description' },
      meta: { eyebrow: { ar: 'تمهيد', en: 'Intro' }, highlight: { ar: 'مميز', en: 'Highlight' } },
      items: [
        { meta: { role: 'goal' } },
        { meta: { role: 'audience' } },
        { meta: { role: 'goal' } },
      ],
    }
    const { result } = withSetup(() => useLandingText(), { locale: 'en' })
    expect(result.heading(section)).toEqual({
      eyebrow: 'Intro',
      title: 'Title',
      highlight: 'Highlight',
      lede: 'Description',
    })
    expect(result.itemsWithRole(section, 'goal')).toHaveLength(2)
    expect(result.firstWithRole(section, 'missing')).toBeNull()
    expect(result.metaText(null, 'eyebrow')).toBe('')
  })
})

describe('useLevelDial', () => {
  it('jumps straight to the target with reduced motion and clamps it', () => {
    stubMatchMedia({ width: 1440, reducedMotion: true })
    const tiers = ref(landingTiers(RANGES))
    const { result } = withSetup(() => useLevelDial(tiers, () => 84))
    expect(result.tier.value.key).toBe('gold')
    result.go(150)
    expect(result.value.value).toBe(100)
    expect(result.tier.value.key).toBe('diamond')
    result.go(-5)
    expect(result.value.value).toBe(0)
    expect(result.tier.value.key).toBe('participation')
  })

  it('eases towards the target frame by frame when motion is allowed', async () => {
    stubMatchMedia({ width: 1440, reducedMotion: false })
    const frames = []
    vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
    vi.stubGlobal('cancelAnimationFrame', () => {})
    const tiers = ref(landingTiers(RANGES))
    const { result } = withSetup(() => useLevelDial(tiers, () => 84))
    result.go(40)
    frames.shift()()
    expect(result.current.value).toBeLessThan(84)
    expect(result.current.value).toBeGreaterThan(40)
  })
})

describe('useKitStory', () => {
  it('shows the final step at once with reduced motion', () => {
    stubMatchMedia({ width: 1440, reducedMotion: true })
    const { result } = withSetup(() => useKitStory(ref(document.createElement('div')), () => 3))
    expect(result.step.value).toBe(3)
  })

  it('advances while visible, loops and pauses when hidden', () => {
    vi.useFakeTimers()
    stubMatchMedia({ width: 1440, reducedMotion: false })
    vi.stubGlobal('IntersectionObserver', Observer)
    const { result } = withSetup(() => useKitStory(ref(document.createElement('div')), () => 3))
    expect(result.step.value).toBe(-1)
    Observer.last.callback([{ isIntersecting: true }])
    vi.advanceTimersByTime(350)
    expect(result.step.value).toBe(0)
    vi.advanceTimersByTime(2600 * 3)
    expect(result.step.value).toBe(3)
    vi.advanceTimersByTime(3600)
    expect(result.step.value).toBe(-1)
    Observer.last.callback([{ isIntersecting: false }])
    vi.advanceTimersByTime(10000)
    expect(result.step.value).toBe(-1)
    result.select(2)
    expect(result.step.value).toBe(2)
  })
})

describe('useLensCycle', () => {
  it('rotates while visible until the visitor picks a lens', () => {
    vi.useFakeTimers()
    stubMatchMedia({ width: 1440, reducedMotion: false })
    vi.stubGlobal('IntersectionObserver', Observer)
    const { result } = withSetup(() => useLensCycle(ref(document.createElement('div')), () => 5))
    vi.advanceTimersByTime(4800)
    expect(result.active.value).toBe(0)
    Observer.last.callback([{ isIntersecting: true }])
    vi.advanceTimersByTime(4800)
    expect(result.active.value).toBe(1)
    result.select(3)
    vi.advanceTimersByTime(4800 * 3)
    expect(result.active.value).toBe(3)
  })

  it('never rotates with reduced motion', () => {
    vi.useFakeTimers()
    stubMatchMedia({ width: 1440, reducedMotion: true })
    const { result } = withSetup(() => useLensCycle(ref(document.createElement('div')), () => 5))
    vi.advanceTimersByTime(20000)
    expect(result.active.value).toBe(0)
  })
})

describe('useLandingReady', () => {
  it('runs callbacks after the layout marks the page ready', async () => {
    const calls = []
    const Child = defineComponent({
      setup() {
        useLandingReady().onReady(() => calls.push('child'))
        return () => h('span')
      },
    })
    let marker
    const Parent = defineComponent({
      setup() {
        marker = provideLandingReady()
        return () => h(Child)
      },
    })
    mount(Parent)
    expect(calls).toEqual([])
    marker.markReady()
    await nextTick()
    expect(calls).toEqual(['child'])
  })

  it('runs at once outside the landing layout', () => {
    const calls = []
    withSetup(() => useLandingReady().onReady(() => calls.push('now')))
    expect(calls).toEqual(['now'])
  })
})

describe('useLandingPrices', () => {
  beforeEach(() => {
    servicePricesService.getServicePrices.mockReset()
  })

  it('maps the advanced path to the award price key', async () => {
    servicePricesService.getServicePrices.mockResolvedValue({
      data: { paths: { award: { isFree: false, price: null }, self: { isFree: true } } },
    })
    const { result } = withSetup(() => useLandingPrices())
    await flushPromises()
    expect(result.pricingOf('self').isFree).toBe(true)
    expect(result.pricingOf('advanced')).toEqual({ isFree: false, price: null })
    expect(result.pricingOf('unknown')).toBeNull()
  })

  it('keeps working without prices when the request fails', async () => {
    const failure = new Error('offline')
    servicePricesService.getServicePrices.mockImplementation(async () => {
      throw failure
    })
    const { result } = withSetup(() => useLandingPrices())
    await flushPromises()
    expect(result.pricingOf('self')).toBeNull()
    expect(logger.error).toHaveBeenCalledWith(failure, { area: 'landing.prices' })
  })
})
