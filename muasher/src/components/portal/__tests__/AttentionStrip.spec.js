import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AttentionStrip from '@/components/portal/AttentionStrip.vue'
import { dashboardService } from '@/services/dashboardService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getActionCenter: vi.fn() },
}))

function item(key, context = {}, extra = {}) {
  return {
    key,
    count: 1,
    severity: 'warning',
    link: '/assessment/accredited',
    dueAt: null,
    context: { reference: null, remainingSubs: null, percent: null, ...context },
    ...extra,
  }
}

const ITEMS = [
  item('submit_accredited_draft', { reference: 'ACC-2026-000014', remainingSubs: 4 }),
  item('read_contact_replies', { reference: 'CNT-2026-000052' }, { link: '/messages' }),
  item('complete_profile', { percent: 80 }, { link: '/entity/profile' }),
  item('complete_payment', { reference: 'PAY-2026-000001' }, { link: '/payments' }),
]

async function factory(items, locale = 'ar', path = '/dashboard') {
  dashboardService.getActionCenter.mockResolvedValue({ data: items, meta: {} })
  const globals = createTestGlobals({ locale })
  await globals.router.push(path)
  const wrapper = mount(AttentionStrip, { global: globals })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('AttentionStrip', () => {
  it('stays hidden when nothing needs attention', async () => {
    const wrapper = await factory([])
    expect(wrapper.find('.attention-strip').exists()).toBe(false)
  })

  it('shows up to three items with Arabic plural forms and isolated references', async () => {
    const wrapper = await factory(ITEMS)
    const desktop = wrapper.findAll('.attention-strip__list--desktop .attention-item')
    expect(desktop).toHaveLength(3)
    expect(desktop[0].text()).toContain('4 معايير فرعية بلا شواهد')
    expect(desktop[0].find('bdi').text()).toBe('ACC-2026-000014')
    expect(desktop[0].attributes('href')).toBe('/assessment/accredited')
    expect(desktop[2].text()).toContain('80%')
    expect(wrapper.find('.attention-strip__count').text()).toBe('4')
  })

  it('hides «أكمل ملف الجهة» on the entity profile page only (P-8)', async () => {
    const onProfile = await factory(ITEMS, 'ar', '/entity/profile')
    expect(onProfile.find('.attention-strip__count').text()).toBe('3')
    expect(onProfile.text()).not.toContain('80%')
    const elsewhere = await factory(ITEMS, 'ar', '/payments')
    expect(elsewhere.find('.attention-strip__count').text()).toBe('4')
  })

  it('expands every item inline with «عرض الكل»', async () => {
    const wrapper = await factory(ITEMS)
    const toggle = wrapper.find('.attention-strip__toggle')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    expect(wrapper.findAll('.attention-strip__list--desktop .attention-item')).toHaveLength(4)
    expect(toggle.attributes('aria-expanded')).toBe('true')
  })

  it('summarises the other items on mobile', async () => {
    const wrapper = await factory(ITEMS.slice(0, 3))
    expect(wrapper.findAll('.attention-strip__mobile .attention-item')).toHaveLength(1)
    expect(wrapper.find('.attention-strip__summary').text()).toBe(
      '+ ردّ جديد على رسائلك · ملف الجهة',
    )
  })

  it('uses the English singular and plural forms', async () => {
    const wrapper = await factory(
      [item('submit_accredited_draft', { reference: 'ACC-1', remainingSubs: 1 })],
      'en',
    )
    expect(wrapper.find('.attention-item').text()).toContain('1 sub-criterion without evidence')
  })
})
