import { vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { homePageFixture, siteSettingsFixture } from '@/mocks/fixtures/content'
import { usePublicHomeStore } from '@/stores/publicHome'

export const LEVELS = [
  { level: 'bronze', label: { ar: 'المستوى البرونزي', en: 'Bronze level' }, minPercent: '60.00' },
  { level: 'silver', label: { ar: 'المستوى الفضي', en: 'Silver level' }, minPercent: '70.00' },
  { level: 'gold', label: { ar: 'المستوى الذهبي', en: 'Gold level' }, minPercent: '80.00' },
  { level: 'diamond', label: { ar: 'المستوى الماسي', en: 'Diamond level' }, minPercent: '90.00' },
]

export const PRICES = {
  accredited: null,
  bag: null,
  paths: {
    self: { price: null, isFree: true, availability: 'available' },
    accredited: {
      price: {
        priceBeforeVat: '1000.00',
        vatRate: '15.00',
        vatAmount: '150.00',
        total: '1150.00',
        currency: 'SAR',
      },
      isFree: false,
      availability: 'available',
    },
    award: { price: null, isFree: false, availability: 'available' },
  },
}

export const FEATURED = [
  { id: 1, name: { ar: 'جهة مسجّلة', en: 'Registered entities' }, value: '120', unit: null },
  { id: 2, name: { ar: 'شهادة صادرة', en: 'Certificates issued' }, value: '64', unit: null },
  { id: 3, name: { ar: 'مقيّماً مؤهلاً', en: 'Qualified assessors' }, value: '30', unit: null },
]

export function landingSection(key) {
  const section = homePageFixture.sections.find((entry) => entry.key === key)
  return JSON.parse(JSON.stringify(section))
}

class StubObserver {
  constructor(callback) {
    this.callback = callback
    StubObserver.instances.push(this)
  }

  observe() {}

  disconnect() {}

  unobserve() {}

  trigger(isIntersecting = true) {
    this.callback([{ isIntersecting }])
  }
}
StubObserver.instances = []

export function stubBrowser({ reducedMotion = true, width = 1440 } = {}) {
  StubObserver.instances = []
  stubMatchMedia({ width, reducedMotion })
  vi.stubGlobal('IntersectionObserver', StubObserver)
  vi.stubGlobal('ResizeObserver', StubObserver)
  return StubObserver
}

export function mountLanding(
  component,
  { props = {}, locale = 'ar', settings = siteSettingsFixture, attach = false } = {},
) {
  const globals = createTestGlobals({ locale })
  const store = usePublicHomeStore(globals.pinia)
  store.$patch({ sections: homePageFixture.sections, siteSettings: settings, status: 'success' })
  const wrapper = mount(component, {
    props,
    global: globals,
    attachTo: attach ? document.body : undefined,
  })
  return { wrapper, globals, store }
}
