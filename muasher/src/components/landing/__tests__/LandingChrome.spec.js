import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { contentService } from '@/services/contentService'
import { kpisService } from '@/services/kpisService'
import { servicePricesService } from '@/services/servicePricesService'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { siteSettingsFixture } from '@/mocks/fixtures/content'
import LandingHeader from '../LandingHeader.vue'
import LandingFooter from '../LandingFooter.vue'
import LandingAssistant from '../LandingAssistant.vue'
import LandingLoader from '../LandingLoader.vue'
import LandingLink from '../LandingLink.vue'
import LandingThemeSwitch from '../LandingThemeSwitch.vue'
import LandingGlyph from '../LandingGlyph.vue'
import LandingSectionHead from '../LandingSectionHead.vue'
import LandingJourneyScreen from '../LandingJourneyScreen.vue'
import LandingGapMap from '../LandingGapMap.vue'
import LandingLayout from '@/components/layout/LandingLayout.vue'
import { landingEntries, landingSectionView } from '../landingSectionRegistry'
import {
  FEATURED,
  LEVELS,
  PRICES,
  landingSection,
  mountLanding,
  stubBrowser,
} from './landingTestKit'
import { homePageFixture } from '@/mocks/fixtures/content'
import { createTestGlobals } from '@/test-utils'
import { i18n as appI18n } from '@/i18n'
import { applyLocale } from '@/i18n/activeLocale'

vi.mock('@/services/contentService', () => ({
  contentService: { getHome: vi.fn(), getCertificateLevels: vi.fn(), getLegalText: vi.fn() },
}))
vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))
vi.mock('@/services/kpisService', () => ({ kpisService: { getFeatured: vi.fn() } }))

beforeEach(() => {
  stubBrowser()
  contentService.getCertificateLevels.mockResolvedValue({ data: { levels: LEVELS } })
  servicePricesService.getServicePrices.mockResolvedValue({ data: PRICES })
  kpisService.getFeatured.mockResolvedValue({ data: FEATURED, meta: { minimum: 3 } })
})

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('LandingHeader', () => {
  it('lists the header links managed in the site settings', async () => {
    const { wrapper } = mountLanding(LandingHeader)
    await flushPromises()
    const links = wrapper.findAll('.lp-top__link')
    expect(links).toHaveLength(siteSettingsFixture.headerLinks.length)
    expect(links[0].text()).toBe(siteSettingsFixture.headerLinks[0].label.ar)
    expect(links[0].attributes('href')).toBe('#model')
  })

  it('offers sign-in and start buttons to guests and the dashboard to signed-in users', async () => {
    const { wrapper, globals } = mountLanding(LandingHeader)
    expect(wrapper.findAll('.lp-top__actions .lp-btn')).toHaveLength(2)
    useAuthStore(globals.pinia).setUser({
      id: 1,
      name: 'سارة',
      roles: ['system_admin'],
      permissions: [],
    })
    await nextTick()
    const buttons = wrapper.findAll('.lp-top__actions .lp-btn')
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('لوحة المعلومات')
  })

  it('opens and closes the mobile menu with the burger and Escape', async () => {
    const { wrapper } = mountLanding(LandingHeader)
    const burger = wrapper.find('.lp-top__burger')
    expect(burger.attributes('aria-expanded')).toBe('false')
    expect(burger.attributes('aria-controls')).toBe('lp-nav')
    await burger.trigger('click')
    expect(burger.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('.lp-top').classes()).toContain('is-nav-open')
    await wrapper.find('.lp-top').trigger('keydown', { key: 'Escape' })
    expect(burger.attributes('aria-expanded')).toBe('false')
  })

  it('switches the language from the header', async () => {
    applyLocale('ar')
    const { wrapper } = mountLanding(LandingHeader)
    const button = wrapper.find('.lp-top__locale')
    expect(button.text()).toBe('EN')
    await button.trigger('click')
    await flushPromises()
    expect(appI18n.global.locale.value).toBe('en')
    applyLocale('ar')
  })
})

describe('LandingThemeSwitch', () => {
  it('toggles the theme and reports it as a switch', async () => {
    const { wrapper, globals } = mountLanding(LandingThemeSwitch)
    const ui = useUiStore(globals.pinia)
    ui.setTheme('light')
    await nextTick()
    const button = wrapper.find('button')
    expect(button.attributes('role')).toBe('switch')
    expect(button.attributes('aria-checked')).toBe('false')
    expect(button.attributes('aria-label')).toBe('التبديل إلى الوضع الداكن')
    await button.trigger('click')
    await flushPromises()
    expect(ui.theme).toBe('dark')
    expect(button.attributes('aria-checked')).toBe('true')
    expect(button.attributes('aria-label')).toBe('التبديل إلى الوضع الفاتح')
  })
})

describe('LandingFooter', () => {
  it('shows the platform description, quick links, legal links and the copyright note', async () => {
    const { wrapper } = mountLanding(LandingFooter)
    await flushPromises()
    expect(wrapper.text()).toContain(siteSettingsFixture.platformDescription.ar)
    expect(wrapper.findAll('.lp-foot__nav').at(0).findAll('a')).toHaveLength(
      siteSettingsFixture.quickLinks.length,
    )
    expect(wrapper.find('.lp-foot__nav--legal').exists()).toBe(true)
    expect(wrapper.find('.lp-foot__base').text()).toContain(siteSettingsFixture.footerNote.ar)
    expect(wrapper.find('.lp-foot__tts').attributes('rel')).toContain('noopener')
  })

  it('falls back to the default copyright line', async () => {
    const { wrapper } = mountLanding(LandingFooter, {
      settings: { ...siteSettingsFixture, footerNote: null },
    })
    expect(wrapper.find('.lp-foot__base').text()).toContain('جميع الحقوق محفوظة')
  })
})

describe('LandingAssistant', () => {
  async function opened() {
    const section = landingSection('assistant')
    const { wrapper } = mountLanding(LandingAssistant, { props: { section }, attach: true })
    await wrapper.find('.lp-assist-btn').trigger('click')
    await nextTick()
    return { wrapper, section }
  }

  it('opens a dialog, focuses the question field and closes with Escape', async () => {
    const { wrapper } = await opened()
    const button = wrapper.find('.lp-assist-btn')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(document.activeElement).toBe(wrapper.find('.lp-assist input').element)
    await wrapper.find('.lp-assist').trigger('keydown', { key: 'Escape' })
    await nextTick()
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(button.element)
  })

  it('answers a suggested question with the CMS answer', async () => {
    const { wrapper, section } = await opened()
    await wrapper.findAll('.lp-assist__sugs button')[0].trigger('click')
    expect(wrapper.find('.lp-assist__reply').text()).toContain(section.items[0].description.ar)
  })

  it('answers a typed question with the fallback reply and the contact link, without any remote call', async () => {
    const fetchSpy = vi.fn()
    vi.stubGlobal('fetch', fetchSpy)
    const { wrapper, section } = await opened()
    await wrapper.find('.lp-assist input').setValue('كم تستغرق المراجعة؟')
    await wrapper.find('.lp-assist form').trigger('submit')
    const reply = wrapper.find('.lp-assist__reply')
    expect(reply.text()).toContain(section.meta.fallbackReply.ar)
    expect(reply.find('a').attributes('href')).toBe('#contact')
    expect(fetchSpy).not.toHaveBeenCalled()
  })

  it('ignores an empty question', async () => {
    const { wrapper } = await opened()
    await wrapper.find('.lp-assist form').trigger('submit')
    expect(wrapper.find('.lp-assist__reply').exists()).toBe(false)
  })
})

describe('LandingLoader', () => {
  it('finishes at once with reduced motion when the content is ready', async () => {
    const wrapper = mount(LandingLoader, { props: { done: true } })
    await nextTick()
    expect(wrapper.emitted('finished')).toHaveLength(1)
    expect(wrapper.find('.lp-loader').exists()).toBe(false)
  })

  it('waits for the content before finishing with reduced motion', async () => {
    const wrapper = mount(LandingLoader, { props: { done: false } })
    expect(wrapper.emitted('finished')).toBeUndefined()
    await wrapper.setProps({ done: true })
    expect(wrapper.emitted('finished')).toHaveLength(1)
  })
})

describe('LandingLink', () => {
  function mountLink(to) {
    const globals = createTestGlobals()
    const wrapper = mount(LandingLink, {
      props: { to },
      slots: { default: 'رابط' },
      global: globals,
      attachTo: document.body,
    })
    return { wrapper, router: globals.router }
  }

  it('scrolls to an anchor on the page', async () => {
    const target = document.createElement('section')
    target.id = 'paths'
    target.scrollIntoView = vi.fn()
    document.body.appendChild(target)
    const { wrapper } = mountLink('/#paths')
    expect(wrapper.attributes('href')).toBe('#paths')
    await wrapper.trigger('click')
    expect(target.scrollIntoView).toHaveBeenCalled()
    expect(wrapper.emitted('navigate')).toHaveLength(1)
  })

  it('pushes internal routes through the router', async () => {
    const { wrapper, router } = mountLink('/kpis')
    const push = vi.spyOn(router, 'push')
    await wrapper.trigger('click')
    expect(push).toHaveBeenCalledWith('/kpis')
  })

  it('opens external links in a new tab safely', () => {
    const { wrapper } = mountLink('https://mytts.sa')
    expect(wrapper.attributes('target')).toBe('_blank')
    expect(wrapper.attributes('rel')).toBe('noopener noreferrer')
  })
})

describe('small building blocks', () => {
  it('draws the named glyph and a default one for unknown names', () => {
    expect(mount(LandingGlyph, { props: { name: 'people' } }).findAll('circle')).toHaveLength(3)
    expect(
      mount(LandingGlyph, { props: { name: 'unknown' } }).findAll('path').length,
    ).toBeGreaterThan(0)
  })

  it('renders the section head with the softer highlight', () => {
    const globals = createTestGlobals()
    const wrapper = mount(LandingSectionHead, {
      props: { eyebrow: 'تمهيد', title: 'عنوان', highlight: 'مميز', lede: 'وصف', titleId: 'x' },
      global: globals,
    })
    expect(wrapper.find('.lp-kicker').text()).toBe('تمهيد')
    expect(wrapper.find('h2#x .lp-soft').text()).toBe('مميز')
    expect(wrapper.find('.lp-head__lede').text()).toBe('وصف')
  })

  it.each(['register', 'choose_path', 'evidence', 'review', 'result', 'improvement'])(
    'renders the %s illustration screen as decoration',
    (screen) => {
      const wrapper = mount(LandingJourneyScreen, {
        props: { screen },
        global: createTestGlobals(),
      })
      expect(wrapper.find('.lp-screen').attributes('aria-hidden')).toBe('true')
      expect(wrapper.find('h4').text()).not.toBe('')
    },
  )

  it('renders the gap map rows and phases', () => {
    const wrapper = mount(LandingGapMap, { global: createTestGlobals() })
    expect(wrapper.findAll('.lp-gapmap__row')).toHaveLength(5)
    expect(wrapper.findAll('.lp-gapmap__phase')).toHaveLength(3)
  })
})

describe('landing section registry', () => {
  it('maps every section of the landing content to a view in order', () => {
    const enabled = homePageFixture.sections.filter((section) => section.enabled)
    const entries = landingEntries(enabled)
    expect(entries.map((entry) => entry.anchor)).toEqual([
      'hero',
      'stats',
      'about',
      'model',
      'paths',
      'journey',
      'begin',
      'levels',
      'kit',
      'lenses',
      'leaders',
      'start',
      'contact',
    ])
  })

  it('ignores unknown types and gives a duplicated anchor only once', () => {
    const entries = landingEntries([
      { id: 1, type: 'faq', meta: null },
      { id: 2, type: 'paths', meta: null },
      { id: 3, type: 'paths', meta: null },
    ])
    expect(entries.map((entry) => entry.anchor)).toEqual(['paths', null])
    expect(landingSectionView({ type: 'rich_text', meta: { variant: 'band' } })).not.toBeNull()
    expect(landingSectionView({ type: 'rich_text', meta: null })).toBeNull()
  })
})

describe('LandingLayout', () => {
  it('wraps the page with the skip link, header, main landmark and footer', async () => {
    const { wrapper } = mountLanding(LandingLayout, { attach: true })
    await flushPromises()
    expect(wrapper.find('.lp-skip').attributes('href')).toBe('#lp-main')
    expect(wrapper.find('header.lp-top').exists()).toBe(true)
    expect(wrapper.find('main#lp-main').exists()).toBe(true)
    expect(wrapper.find('footer.lp-foot').exists()).toBe(true)
    expect(wrapper.find('.lp').classes()).toContain('is-ready')
    expect(wrapper.find('.lp-assist-btn').exists()).toBe(true)
  })
})
