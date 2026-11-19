import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Notifications from '@/views/settings/Notifications.vue'
import { notificationTypesService } from '@/services/notificationTypesService'
import { createTestGlobals } from '@/test-utils'
import { chooseLabelledOption } from '@/test-utils/dropdown'
import { applyFilterDialog, openFilterDialog } from '@/test-utils/listToolbar'

vi.mock('@/services/notificationTypesService', () => ({
  notificationTypesService: { list: vi.fn() },
}))

const TYPES = [
  {
    code: 'CNT-01',
    module: 'CNT',
    category: 'action_required',
    channels: ['in_app', 'email'],
    enabledChannels: ['in_app'],
    audiences: ['contact_handler'],
    isMandatory: false,
    userConfigurableChannels: ['email'],
  },
  {
    code: 'SEC-07',
    module: 'SEC',
    category: 'alert',
    channels: ['in_app', 'email'],
    enabledChannels: ['in_app', 'email'],
    audiences: ['system_admin', 'super_admin'],
    isMandatory: true,
    userConfigurableChannels: [],
  },
]

async function mountView(locale = 'ar') {
  const wrapper = mount(Notifications, {
    global: createTestGlobals({ locale }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  notificationTypesService.list.mockResolvedValue({ data: TYPES, meta: { total: 2 } })
})

describe('notification types catalogue', () => {
  it('lists the types with their channels, audiences and preference rule', async () => {
    const wrapper = await mountView()
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('CNT-01')
    expect(rows[0].text()).toContain('رسالة تواصل جديدة')
    expect(rows[0].text()).toContain('متابعو رسائل تواصل معنا')
    expect(rows[0].text()).toContain('يستطيع المستخدم إيقاف: البريد الإلكتروني')
    expect(rows[1].text()).toContain('إلزامي')
    expect(wrapper.text()).toContain('عدد الأنواع: 2')
    expect(rows[0].findAll('.notification-catalogue__chips')[0].findAll('li')).toHaveLength(1)
    expect(rows[0].find('a').attributes('href')).toBe('/admin/settings/notifications/CNT-01')
    expect(rows[0].find('a').text()).toBe('تعديل النصوص')
  })

  it('offers no editing control at all', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('سجل التدقيق')
  })

  it('filters by module, category and audience on the server', async () => {
    const wrapper = await mountView('en')
    await openFilterDialog(wrapper)
    await chooseLabelledOption(wrapper, 'Module', 'CNT')
    await chooseLabelledOption(wrapper, 'Audience', 'contact_handler')
    await applyFilterDialog()
    expect(notificationTypesService.list).toHaveBeenLastCalledWith({
      module: 'CNT',
      category: '',
      audience: 'contact_handler',
    })
    expect(wrapper.text()).toContain('Clear filters')
  })

  it('shows the forbidden state for an account without the permission', async () => {
    notificationTypesService.list.mockRejectedValue({ normalized: { status: 403, message: 'No' } })
    const wrapper = await mountView('en')
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })
})
