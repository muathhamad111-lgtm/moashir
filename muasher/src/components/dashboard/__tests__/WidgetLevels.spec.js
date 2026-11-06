import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import DashboardWidget from '@/components/dashboard/DashboardWidget.vue'

const DATA = [
  { key: 'none', count: 4 },
  { key: 'in_progress', count: 1 },
  { key: 'participation', count: 2 },
  { key: 'bronze', count: 3 },
  { key: 'silver', count: 0 },
  { key: 'gold', count: 2 },
  { key: 'diamond', count: 1 },
]

function render(roles, { locale = 'ar' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  return mount(DashboardWidget, {
    props: { board: 'system_admin', widget: { key: 'entities_by_level', data: DATA } },
    global: globals,
  })
}

describe('entities_by_level widget', () => {
  it('renders the seven buckets in the fixed order with their counts, zeros included', () => {
    const wrapper = render(['system_admin'])
    expect(wrapper.find('.dashboard-widget__title').text()).toBe('الجهات حسب المستوى')
    const rows = wrapper.findAll('.widget-levels__row')
    expect(rows.map((row) => row.attributes('data-level'))).toEqual(DATA.map((item) => item.key))
    expect(rows.map((row) => row.find('bdi').text())).toEqual(['4', '1', '2', '3', '0', '2', '1'])
  })

  it('uses tier colours on the metal bars only', () => {
    const wrapper = render(['system_admin'])
    const tones = wrapper
      .findAll('.widget-levels__row')
      .map((row) => row.classes().find((name) => name.startsWith('level-tone--')))
    expect(tones).toEqual([
      'level-tone--neutral',
      'level-tone--neutral',
      'level-tone--neutral',
      'level-tone--bronze',
      'level-tone--silver',
      'level-tone--gold',
      'level-tone--diamond',
    ])
  })

  it('links every level except in progress to the filtered entities list', () => {
    const wrapper = render(['system_admin'])
    const links = wrapper.findAll('a.widget-levels__label--link')
    expect(links).toHaveLength(6)
    expect(links.map((link) => link.attributes('href'))).toContain('/admin/entities?level=gold')
    const progress = wrapper.find('[data-level="in_progress"]')
    expect(progress.find('a').exists()).toBe(false)
    expect(wrapper.find('.widget-levels__hint').exists()).toBe(true)
  })

  it('anchors each filled bar on the inline-start side (right in ar, left in en)', () => {
    const fillOf = (wrapper) => wrapper.find('[data-level="gold"] .widget-levels__fill')
    const arabic = fillOf(render(['system_admin']))
    expect(arabic.attributes('width')).toBe('50%')
    expect(arabic.attributes('x')).toBe('50%')
    const english = fillOf(render(['system_admin'], { locale: 'en' }))
    expect(english.attributes('x')).toBe('0')
  })

  it('shows plain labels without links to a role that cannot open the entities list', () => {
    const wrapper = render(['content_officer'], { locale: 'en' })
    expect(wrapper.findAll('a.widget-levels__label--link')).toHaveLength(0)
    expect(wrapper.find('.widget-levels__hint').exists()).toBe(false)
    expect(wrapper.find('[data-level="gold"] .level-badge').text()).toBe('Gold')
  })
})
