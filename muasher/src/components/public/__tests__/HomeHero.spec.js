import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import HomeHero from '@/components/public/HomeHero.vue'

vi.mock('@/services/contentService', () => ({
  contentService: { getCertificateLevels: vi.fn(() => new Promise(() => {})) },
}))

const DESKTOP_WIDTH = 1440
const PHONE_WIDTH = 390

const SECTION = {
  title: { en: 'Measure your organizational excellence,' },
  description: { en: 'Moashir measures institutional maturity.' },
  ctaLabel: { en: 'Start the free self assessment' },
  items: [],
  meta: {
    preview: {
      title: { en: 'Institutional maturity' },
      score: 78,
      bars: [{ label: { en: 'Administrative leadership' }, value: 92 }],
      badges: [],
    },
  },
}

function localized(content) {
  return typeof content === 'string' ? content : (content?.en ?? '')
}

function mountHero() {
  const globals = createTestGlobals({ locale: 'en' })
  return mount(HomeHero, { props: { section: SECTION, localized }, global: globals })
}

afterEach(() => vi.unstubAllGlobals())

describe('HomeHero motion control', () => {
  it('offers the pause / play control for the ambient motion on desktop', async () => {
    stubMatchMedia({ width: DESKTOP_WIDTH })
    const wrapper = mountHero()
    const control = wrapper.find('.home-hero__motion')
    expect(control.text()).toBe('Pause animation')
    expect(control.attributes('aria-pressed')).toBe('false')
    await control.trigger('click')
    expect(control.text()).toBe('Play animation')
    expect(control.attributes('aria-pressed')).toBe('true')
    await control.trigger('click')
    expect(control.attributes('aria-pressed')).toBe('false')
    wrapper.unmount()
  })

  it('renders no control on phones while the preview card stays in place', () => {
    stubMatchMedia({ width: PHONE_WIDTH })
    const wrapper = mountHero()
    expect(wrapper.find('.home-hero__motion').exists()).toBe(false)
    expect(wrapper.find('.hero-preview').exists()).toBe(true)
    wrapper.unmount()
  })

  it('keeps the control on desktop when reduced motion is requested, as before', () => {
    stubMatchMedia({ width: DESKTOP_WIDTH, reducedMotion: true })
    const wrapper = mountHero()
    expect(wrapper.find('.home-hero__motion').exists()).toBe(true)
    wrapper.unmount()
  })

  it('follows the viewport across the tablet breakpoint and releases its listener', async () => {
    const media = stubMatchMedia({ width: DESKTOP_WIDTH })
    const wrapper = mountHero()
    media.resize(PHONE_WIDTH)
    await nextTick()
    expect(wrapper.find('.home-hero__motion').exists()).toBe(false)
    media.resize(DESKTOP_WIDTH)
    await nextTick()
    expect(wrapper.find('.home-hero__motion').exists()).toBe(true)
    wrapper.unmount()
    expect(media.listenerCount()).toBe(0)
  })
})
