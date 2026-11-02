import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import KpiCard from '@/components/kpis/KpiCard.vue'
import { createTestGlobals } from '@/test-utils'

const kpi = {
  id: 1,
  name: { ar: 'الجمعيات المشاركة', en: 'Participating associations' },
  value: 486,
  target: 700,
  baseline: 320,
  unit: { ar: 'جمعية', en: 'associations' },
  description: { ar: 'وصف عربي', en: 'English description' },
  category: 'beneficiaries',
  status: 'good',
  lastUpdate: '2025-01-15',
  icon: 'fa-solid fa-people-group',
  color: 'primary',
  cardColor: 'primary',
  order: 1,
  published: true,
}

function localizedFor(locale) {
  return (content) => (typeof content === 'string' ? content : content[locale] || content.ar)
}

function mountCard(locale = 'en', overrides = {}) {
  return mount(KpiCard, {
    props: { kpi: { ...kpi, ...overrides }, localized: localizedFor(locale) },
    global: createTestGlobals({ locale }),
  })
}

describe('KpiCard', () => {
  it('renders the localized name, unit and description (en)', () => {
    const wrapper = mountCard('en')
    expect(wrapper.find('.kpi-card__name').text()).toBe('Participating associations')
    expect(wrapper.find('.kpi-card__unit').text()).toBe('associations')
    expect(wrapper.find('.kpi-card__description').text()).toBe('English description')
  })

  it('renders Arabic content when the resolver targets ar', () => {
    const wrapper = mountCard('ar')
    expect(wrapper.find('.kpi-card__name').text()).toBe('الجمعيات المشاركة')
  })

  it('applies the card color variant class', () => {
    expect(mountCard().find('.kpi-card').classes()).toContain('kpi-card--primary')
  })

  it('falls back to the color field then primary for an unknown cardColor', () => {
    expect(mountCard('en', { cardColor: 'chartreuse' }).find('.kpi-card').classes()).toContain(
      'kpi-card--primary',
    )
    expect(
      mountCard('en', { cardColor: 'chartreuse', color: 'success' }).find('.kpi-card').classes(),
    ).toContain('kpi-card--success')
  })

  it('renders a status badge with text (not color alone)', () => {
    const wrapper = mountCard('en')
    const badge = wrapper.find('.kpi-card__status')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('Good')
    expect(badge.classes()).toContain('badge--info')
  })

  it('shows the resolved value when IntersectionObserver is absent', async () => {
    const wrapper = mountCard('en')
    await nextTick()
    expect(wrapper.find('.kpi-card__number').text()).toBe('486')
  })

  it('exposes the exact value to assistive tech regardless of the animation', () => {
    const wrapper = mountCard('en')
    expect(wrapper.find('.kpi-card__number-sr').text()).toBe('486')
  })

  it('computes the progress meter against the target and exposes it to a11y', () => {
    const wrapper = mountCard()
    const meter = wrapper.find('.kpi-card__meter')
    expect(meter.attributes('role')).toBe('progressbar')
    expect(meter.attributes('aria-valuenow')).toBe('69')
  })

  it('is a keyboard-accessible link and emits activate on Enter', async () => {
    const wrapper = mountCard('en')
    const card = wrapper.find('.kpi-card')
    expect(card.attributes('role')).toBe('link')
    expect(card.attributes('tabindex')).toBe('0')
    await card.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('activate')).toBeTruthy()
    expect(wrapper.emitted('activate')[0][0].id).toBe(1)
  })

  it('emits activate on click', async () => {
    const wrapper = mountCard('en')
    await wrapper.find('.kpi-card').trigger('click')
    expect(wrapper.emitted('activate')).toBeTruthy()
  })

  it('reveals target, baseline and last update facts', () => {
    const wrapper = mountCard('en')
    const facts = wrapper.findAll('.kpi-card__fact-value').map((el) => el.text())
    expect(facts.some((text) => text.includes('700'))).toBe(true)
    expect(facts.some((text) => text.includes('320'))).toBe(true)
    expect(facts).toContain('2025-01-15')
  })

  it('observes the card and counts up to the value when it enters the viewport', async () => {
    const observe = vi.fn()
    let capturedCallback
    class ObserverStub {
      constructor(cb) {
        capturedCallback = cb
      }
      observe(el) {
        observe(el)
      }
      disconnect() {}
    }
    vi.stubGlobal('IntersectionObserver', ObserverStub)
    vi.stubGlobal('matchMedia', () => ({ matches: false }))
    vi.stubGlobal('requestAnimationFrame', (cb) => cb(performance.now() + 5000))

    const wrapper = mountCard('en')
    expect(observe).toHaveBeenCalled()

    capturedCallback([{ isIntersecting: true }])
    await nextTick()
    expect(wrapper.find('.kpi-card__number').text()).toBe('486')

    vi.unstubAllGlobals()
  })

  it('renders the value immediately when reduced motion is preferred', async () => {
    class ObserverStub {
      observe() {}
      disconnect() {}
    }
    vi.stubGlobal('IntersectionObserver', ObserverStub)
    vi.stubGlobal('matchMedia', () => ({ matches: true }))

    const wrapper = mountCard('en')
    await nextTick()
    expect(wrapper.find('.kpi-card__number').text()).toBe('486')

    vi.unstubAllGlobals()
  })
})
