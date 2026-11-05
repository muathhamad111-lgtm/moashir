import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { WITHHELD_ICONS } from '@/utils/icons/iconRegistry'

function i18nFor(locale) {
  return createI18n({ legacy: false, locale, messages: { ar, en } })
}

async function mountNotice(env, props, locale = 'en') {
  vi.resetModules()
  Object.entries(env).forEach(([name, value]) => vi.stubEnv(name, value))
  const { default: DemoDataNotice } = await import('@/components/ui/DemoDataNotice.vue')
  return mount(DemoDataNotice, { props, global: { plugins: [i18nFor(locale)] } })
}

const HYBRID = {
  VITE_API_BASE_URL: '/api/v1',
  VITE_DEMO_BUILD: 'true',
  VITE_DEMO_MODULES: 'kpis,messaging',
}

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('DemoDataNotice', () => {
  it('marks a page served by a demo module with a translated note', async () => {
    const wrapper = await mountNotice(HYBRID, { modules: ['kpis'] })
    const note = wrapper.get('[role="note"]')
    expect(note.text()).toContain('Demo data')
    expect(note.text()).toContain('this module is not connected yet')
    expect(note.classes()).toContain('demo-notice--page')
    expect(WITHHELD_ICONS).toContain('flask')
    expect(wrapper.find('svg').exists()).toBe(false)
  })

  it('speaks Arabic in the Arabic interface and words sections and actions apart', async () => {
    const section = await mountNotice(HYBRID, { modules: ['kpis'], scope: 'section' }, 'ar')
    expect(section.text()).toContain('بيانات تجريبية')
    expect(section.text()).toContain('هذا القسم')
    const actions = await mountNotice(HYBRID, { modules: ['messaging'], scope: 'actions' })
    expect(actions.text()).toContain('No message is actually sent')
  })

  it('stays hidden on real modules, in the full mock layer and with the real API only', async () => {
    expect(
      (await mountNotice(HYBRID, { modules: ['criteria'] })).find('[role="note"]').exists(),
    ).toBe(false)
    expect((await mountNotice(HYBRID, {})).find('[role="note"]').exists()).toBe(false)
    expect(
      (await mountNotice({ VITE_API_BASE_URL: '' }, { modules: ['kpis'] }))
        .find('[role="note"]')
        .exists(),
    ).toBe(false)
    expect(
      (
        await mountNotice(
          { VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: '' },
          { modules: ['kpis'] },
        )
      )
        .find('[role="note"]')
        .exists(),
    ).toBe(false)
  })
})
