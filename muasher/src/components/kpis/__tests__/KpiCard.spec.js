import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import KpiCard from '@/components/kpis/KpiCard.vue'
import { createTestGlobals } from '@/test-utils'

const kpi = {
  id: 1,
  slug: 'participating-associations',
  name: { ar: 'الجمعيات المشاركة', en: 'Participating associations' },
  value: '486',
  target: '700',
  baseline: '320',
  unit: { ar: 'جمعية', en: 'associations' },
  shortDescription: { ar: 'وصف عربي', en: 'English description' },
  category: { id: 3, key: 'beneficiaries', name: { ar: 'المستفيدون', en: 'Beneficiaries' } },
  status: 'good',
  lastUpdate: '2026-06-30T09:00:00Z',
  icon: 'users-two',
  cardColor: 'primary',
  badgeColor: 'info',
  order: 1,
}

function localizedFor(locale) {
  return (content) => (content ? content[locale] || content.ar : '')
}

function mountCard(locale = 'en', overrides = {}) {
  return mount(KpiCard, {
    props: { kpi: { ...kpi, ...overrides }, localized: localizedFor(locale) },
    global: createTestGlobals({ locale }),
  })
}

describe('KpiCard', () => {
  it('renders the localized name, unit and short description (en)', () => {
    const wrapper = mountCard('en')
    expect(wrapper.find('.kpi-card__name').text()).toBe('Participating associations')
    expect(wrapper.find('.kpi-card__unit').text()).toBe('associations')
    expect(wrapper.find('.kpi-card__description').text()).toBe('English description')
  })

  it('renders Arabic content when the resolver targets ar', () => {
    const wrapper = mountCard('ar')
    expect(wrapper.find('.kpi-card__name').text()).toBe('الجمعيات المشاركة')
  })

  it('applies the card color token class', () => {
    expect(mountCard('en', { cardColor: 'success' }).find('.kpi-card').classes()).toContain(
      'kpi-card--success',
    )
  })

  it('reads the editor token name and falls back to primary for an unknown token', () => {
    expect(
      mountCard('en', { cardColor: undefined, cardColorToken: 'warning' })
        .find('.kpi-card')
        .classes(),
    ).toContain('kpi-card--warning')
    expect(mountCard('en', { cardColor: 'chartreuse' }).find('.kpi-card').classes()).toContain(
      'kpi-card--primary',
    )
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
    const meter = wrapper.find('progress.kpi-card__meter')
    expect(meter.attributes('value')).toBe('69')
    expect(meter.attributes('max')).toBe('100')
    expect(meter.attributes('aria-label')).toBe('Participating associations')
    expect(meter.attributes('style')).toBeUndefined()
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
    expect(facts.some((text) => text.includes('2026'))).toBe(true)
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
