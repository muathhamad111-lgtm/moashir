import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { TOUR_STEP_MS } from '@/constants/homeSections'
import { contentService } from '@/services/contentService'
import HomeTour from '@/components/public/HomeTour.vue'
import HomeJourney from '@/components/public/HomeJourney.vue'
import HomeCtaBanner from '@/components/public/HomeCtaBanner.vue'
import HeroPreviewCard from '@/components/public/HeroPreviewCard.vue'
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'
import HomeFastKit from '@/components/public/HomeFastKit.vue'
import HomeLeadership from '@/components/public/HomeLeadership.vue'

const DESKTOP_WIDTH = 1440
const PHONE_WIDTH = 390

beforeEach(() => stubMatchMedia({ width: DESKTOP_WIDTH, reducedMotion: true }))
afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

vi.mock('@/services/contentService', () => ({
  contentService: { getCertificateLevels: vi.fn() },
}))

const LEVELS = {
  data: {
    levels: [
      { level: 'bronze', label: { en: 'Bronze level' }, minPercent: '60.00' },
      { level: 'silver', label: { en: 'Silver level' }, minPercent: '70.00' },
      { level: 'gold', label: { en: 'Gold level' }, minPercent: '80.00' },
      { level: 'diamond', label: { en: 'Diamond level' }, minPercent: '90.00' },
    ],
  },
}

function localized(content) {
  return typeof content === 'string' ? content : (content?.en ?? '')
}

function item(id, order, title) {
  return {
    id,
    order,
    icon: 'star',
    title: { ar: title, en: title },
    description: { ar: 'وصف', en: `${title} details` },
  }
}

function mountWith(component, props, locale = 'en') {
  const globals = createTestGlobals({ locale })
  return { wrapper: mount(component, { props: { localized, ...props }, global: globals }), globals }
}

describe('HomeSectionHead', () => {
  it('renders the eyebrow, title and texts centred by default', () => {
    const section = {
      title: { en: 'Title' },
      description: { en: 'Body' },
      meta: { eyebrow: { en: 'Eyebrow' } },
    }
    const { wrapper } = mountWith(HomeSectionHead, { section, titleId: 'head-title' })
    expect(wrapper.classes()).toContain('home-section__head--center')
    expect(wrapper.find('.home-section__eyebrow').text()).toBe('Eyebrow')
    expect(wrapper.find('h2').text()).toBe('Title')
    expect(wrapper.find('h2').attributes('id')).toBe('head-title')
    expect(wrapper.find('.home-section__subtitle').exists()).toBe(false)
  })
})

describe('HomeTour', () => {
  const section = {
    title: { en: 'Tour' },
    items: [item('a', 1, 'First'), item('b', 2, 'Second'), item('c', 3, 'Third')],
  }

  it('lists the steps in order, marks the active one and swaps the simplified screen', async () => {
    const { wrapper } = mountWith(HomeTour, { section })
    const steps = wrapper.findAll('.home-tour__step')
    expect(steps.map((step) => step.find('.home-tour__title').text())).toEqual([
      'First',
      'Second',
      'Third',
    ])
    expect(steps[0].attributes('aria-current')).toBe('step')
    expect(wrapper.find('.tour-screen--register').exists()).toBe(true)
    expect(wrapper.find('.home-tour__sr').text()).toContain('First')

    await steps[2].trigger('click')
    expect(steps[2].attributes('aria-current')).toBe('step')
    expect(wrapper.find('.tour-screen--evidence').exists()).toBe(true)
    expect(wrapper.findAll('.home-tour__pager-dot')[2].classes()).toContain('is-active')
    expect(wrapper.find('.home-tour__toggle').attributes('aria-pressed')).toBe('false')
  })

  it('shows the progress bar only under the active step', async () => {
    const { wrapper } = mountWith(HomeTour, { section })
    expect(wrapper.findAll('.home-tour__progress')).toHaveLength(1)
    expect(wrapper.findAll('.home-tour__step')[0].find('.home-tour__progress').exists()).toBe(true)
  })

  it('offers a pause / play control for the automatic tour', async () => {
    const { wrapper } = mountWith(HomeTour, { section }, 'ar')
    const toggle = wrapper.find('.home-tour__toggle')
    expect(toggle.text()).toBe('استئناف التشغيل التلقائي')
    await toggle.trigger('click')
    expect(toggle.text()).toBe('إيقاف التشغيل التلقائي')
    expect(toggle.attributes('aria-pressed')).toBe('true')
  })

  it('renders no pause / play control on phones and still advances by itself', async () => {
    vi.useFakeTimers()
    stubMatchMedia({ width: PHONE_WIDTH })
    const { wrapper } = mountWith(HomeTour, { section })
    expect(wrapper.find('.home-tour__toggle').exists()).toBe(false)
    expect(wrapper.classes()).toContain('is-running')
    vi.advanceTimersByTime(TOUR_STEP_MS)
    await nextTick()
    expect(wrapper.findAll('.home-tour__step')[1].attributes('aria-current')).toBe('step')
    wrapper.unmount()
  })

  it('keeps the tour still on phones when reduced motion is requested', async () => {
    vi.useFakeTimers()
    stubMatchMedia({ width: PHONE_WIDTH, reducedMotion: true })
    const { wrapper } = mountWith(HomeTour, { section })
    expect(wrapper.find('.home-tour__toggle').exists()).toBe(false)
    vi.advanceTimersByTime(TOUR_STEP_MS * 2)
    await nextTick()
    expect(wrapper.findAll('.home-tour__step')[0].attributes('aria-current')).toBe('step')
    wrapper.unmount()
  })

  it('adds and removes the control when the viewport crosses the tablet breakpoint', async () => {
    const media = stubMatchMedia({ width: PHONE_WIDTH })
    const { wrapper } = mountWith(HomeTour, { section })
    media.resize(DESKTOP_WIDTH)
    await nextTick()
    expect(wrapper.find('.home-tour__toggle').attributes('aria-pressed')).toBe('true')
    media.resize(PHONE_WIDTH)
    await nextTick()
    expect(wrapper.find('.home-tour__toggle').exists()).toBe(false)
    wrapper.unmount()
    expect(media.listenerCount()).toBe(0)
  })
})

describe('HomeJourney', () => {
  it('numbers the stages with two digits over the brand pattern', () => {
    const section = {
      title: { en: 'Journey' },
      items: [item('1', 1, 'Start'), item('2', 2, 'Measure'), item('3', 3, 'Lead')],
    }
    const { wrapper } = mountWith(HomeJourney, { section })
    const stages = wrapper.findAll('.home-journey__stage')
    expect(stages).toHaveLength(3)
    expect(stages.map((stage) => stage.find('.home-journey__marker').text())).toEqual([
      '01',
      '02',
      '03',
    ])
    expect(wrapper.find('.home-journey__line').attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('.brand-pattern').attributes('aria-hidden')).toBe('true')
  })
})

describe('HomeCtaBanner', () => {
  const section = {
    title: { en: 'Ready?' },
    ctaLabel: { en: 'Create account' },
    ctaUrl: '/register',
    items: [
      {
        id: 9,
        title: { en: 'Sign in' },
        ctaLabel: { en: 'Sign in' },
        ctaUrl: '/login',
        meta: { role: 'secondary-cta' },
      },
    ],
  }

  it('offers the register and sign-in actions and navigates only to internal paths', async () => {
    const { wrapper, globals } = mountWith(HomeCtaBanner, { section })
    const push = vi.spyOn(globals.router, 'push').mockResolvedValue()
    const buttons = wrapper.findAll('.btn')
    expect(buttons.map((button) => button.text())).toEqual(['Create account', 'Sign in'])
    await buttons[0].trigger('click')
    await buttons[1].trigger('click')
    expect(push.mock.calls).toEqual([['/register'], ['/login']])

    await wrapper.setProps({ section: { ...section, ctaUrl: 'https://example.sa', items: [] } })
    push.mockClear()
    await wrapper.find('.btn').trigger('click')
    expect(push).not.toHaveBeenCalled()
  })
})

describe('HeroPreviewCard', () => {
  const preview = {
    title: { en: 'Institutional maturity' },
    score: 84,
    bars: [{ label: { en: 'Leadership' }, value: 92 }],
    badges: [
      { icon: 'award', title: { en: 'Accredited certificate' } },
      { icon: 'doc', title: { en: 'Feedback report' } },
    ],
  }

  it('labels the card as a maturity report preview and derives the level from the thresholds', async () => {
    contentService.getCertificateLevels.mockResolvedValue(LEVELS)
    const { wrapper } = mountWith(HeroPreviewCard, { preview })
    await flushPromises()
    expect(wrapper.find('.home-pill--preview').text()).toBe('Maturity report preview')
    expect(wrapper.find('.hero-preview').attributes('aria-label')).toBe('Maturity report preview')
    expect(wrapper.find('.score-ring__number').text()).toBe('84%')
    expect(wrapper.find('.score-ring__value').attributes('stroke-dashoffset')).toBe('16')
    expect(wrapper.find('.hero-preview__level .home-pill--gold').text()).toBe('Gold level')
    expect(wrapper.find('.hero-preview__range').text()).toBe('80–89.99%')
    expect(wrapper.find('.hero-preview__bar-value').text()).toBe('92%')
    expect(wrapper.find('.meter-bar__fill').attributes('width')).toBe('92%')
    expect(wrapper.findAll('.hero-preview__badge')).toHaveLength(2)
  })

  it('hides the level when the thresholds cannot be read', async () => {
    contentService.getCertificateLevels.mockRejectedValue(new Error('offline'))
    const { wrapper } = mountWith(HeroPreviewCard, { preview })
    await flushPromises()
    expect(wrapper.find('.hero-preview__level').exists()).toBe(false)
    expect(wrapper.find('.score-ring__number').text()).toBe('84%')
  })
})

describe('HomeFastKit', () => {
  it('marks the kit as coming soon without any purchase action', () => {
    const section = {
      title: { en: 'Tools' },
      meta: { availability: 'coming_soon' },
      items: [item('k1', 1, 'Measurable'), item('k2', 2, 'Guides'), item('k3', 3, 'Gaps')],
    }
    const { wrapper } = mountWith(HomeFastKit, { section })
    expect(wrapper.find('.home-pill--soon').text()).toBe('Coming soon')
    expect(wrapper.findAll('.home-kit__card')).toHaveLength(3)
    expect(wrapper.find('a, button').exists()).toBe(false)
  })
})

describe('HomeLeadership', () => {
  it('introduces the leaders path as coming soon with no link', () => {
    const section = { title: { en: 'Leaders path' }, description: { en: 'Soon.' } }
    const { wrapper } = mountWith(HomeLeadership, { section })
    expect(wrapper.find('h2').text()).toBe('Leaders path')
    expect(wrapper.find('.home-pill--soon').text()).toBe('Coming soon')
    expect(wrapper.find('a').exists()).toBe(false)
  })
})
