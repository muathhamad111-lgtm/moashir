import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Notifications from '@/views/settings/Notifications.vue'
import { notificationTypesService } from '@/services/notificationTypesService'
import { notificationTextsService } from '@/services/notificationTextsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/notificationTypesService', () => ({
  notificationTypesService: { list: vi.fn(), bulkUpdate: vi.fn() },
}))
vi.mock('@/services/notificationTextsService', () => ({
  notificationTextsService: { updateChannels: vi.fn() },
}))

const confirmMock = vi.fn()
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: confirmMock }) }))

function statesOf(inApp, email) {
  return { in_app: inApp, email, sms: false }
}

const TYPES = [
  {
    code: 'REG-01',
    module: 'REG',
    audiences: ['entity', 'system_admin'],
    audienceKinds: ['beneficiary', 'management'],
    isCore: false,
    enabledChannels: ['in_app', 'email'],
    channelStates: { beneficiary: statesOf(true, true), management: statesOf(true, false) },
  },
  {
    code: 'REG-02',
    module: 'REG',
    audiences: ['account_holder'],
    audienceKinds: ['beneficiary', 'management'],
    isCore: true,
    enabledChannels: ['email', 'sms'],
    channelStates: { beneficiary: statesOf(false, true), management: statesOf(false, true) },
  },
  {
    code: 'CNT-01',
    module: 'CNT',
    audiences: ['contact_handler'],
    audienceKinds: ['management'],
    isCore: false,
    enabledChannels: ['in_app'],
    channelStates: { management: statesOf(true, false) },
  },
]

let wrapper

async function mountView() {
  wrapper = mount(Notifications, {
    global: createTestGlobals({ locale: 'ar' }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

async function openGroup(index) {
  await wrapper.findAll('.accordion__trigger')[index].trigger('click')
}

beforeEach(() => {
  notificationTypesService.list.mockResolvedValue({ data: TYPES, meta: { total: 3 } })
  confirmMock.mockResolvedValue(true)
})

afterEach(() => {
  localStorage.clear()
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('notification types catalogue', () => {
  it('groups the types by module with a count, closed until opened', async () => {
    await mountView()
    const titles = wrapper.findAll('.notification-groups__title').map((title) => title.text())
    expect(titles).toEqual(['التسجيل وملف الجهة 2', 'تواصل معنا 1'])
    expect(wrapper.find('.notification-matrix__row').exists()).toBe(false)
    await openGroup(0)
    expect(wrapper.findAll('.notification-matrix__row')).toHaveLength(2)
  })

  it('gives every audience of a type its own channel switches and locks a core type', async () => {
    await mountView()
    await openGroup(0)
    const [registered, otp] = wrapper.findAll('.notification-matrix__row')
    expect(registered.findAll('.notification-matrix__audience')).toHaveLength(2)
    expect(
      registered.findAll('input[role="switch"]').map((input) => input.element.checked),
    ).toEqual([true, true, false, true, false, false])
    expect(otp.text()).toContain('أساسي')
    expect(
      otp
        .findAll('input[role="switch"]')
        .every((input) => input.attributes('disabled') !== undefined),
    ).toBe(true)
  })

  it('switches one channel for one audience', async () => {
    notificationTextsService.updateChannels.mockResolvedValue({
      data: {
        channelSettings: [{ audience: 'management', channel: 'email', isEnabled: true }],
      },
    })
    await mountView()
    await openGroup(0)
    await wrapper.find('input[name="REG-01-management-email"]').setValue(true)
    await flushPromises()
    expect(notificationTextsService.updateChannels).toHaveBeenCalledWith(
      'REG-01',
      { email: true },
      'management',
    )
  })

  it('turns a whole group off after confirmation', async () => {
    notificationTypesService.bulkUpdate.mockResolvedValue({ data: TYPES })
    await mountView()
    await openGroup(0)
    const groupOff = wrapper.findAll('.notification-group-actions button')[1]
    await groupOff.trigger('click')
    await flushPromises()
    expect(confirmMock).toHaveBeenCalled()
    expect(notificationTypesService.bulkUpdate).toHaveBeenCalledWith({
      scope: 'codes',
      codes: ['REG-01', 'REG-02'],
      enabled: false,
    })
  })

  it('does nothing when the bulk change is not confirmed', async () => {
    confirmMock.mockResolvedValue(false)
    await mountView()
    await openGroup(0)
    await wrapper.findAll('.notification-group-actions button')[0].trigger('click')
    await flushPromises()
    expect(notificationTypesService.bulkUpdate).not.toHaveBeenCalled()
  })

  it('finds a type by name or code and opens the groups that match', async () => {
    await mountView()
    wrapper
      .findComponent({ name: 'NotificationCatalogueFilters' })
      .vm.$emit('change', { search: 'cnt-01' })
    await flushPromises()
    expect(wrapper.findAll('.notification-groups__title').map((title) => title.text())).toEqual([
      'تواصل معنا 1',
    ])
    expect(wrapper.findAll('.notification-matrix__row')).toHaveLength(1)
  })

  it('switches to one tab per channel with how many types use it', async () => {
    await mountView()
    await wrapper.findAll('.notification-view-switch__option')[1].trigger('click')
    await flushPromises()
    const tabs = wrapper
      .findAll('.notification-catalogue__channels [role="tab"]')
      .map((tab) => tab.text())
    expect(tabs).toEqual(['داخل المنصة 2/3', 'البريد الإلكتروني 2/3', 'الرسائل النصية 0/3'])
  })

  it('shows the forbidden state for an account without the permission', async () => {
    notificationTypesService.list.mockRejectedValue({ normalized: { status: 403, message: 'No' } })
    await mountView()
    expect(wrapper.find('.notification-groups').exists()).toBe(false)
    expect(wrapper.find('.empty-state').exists()).toBe(true)
  })
})
