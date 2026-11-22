import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NotificationTypeTexts from '@/views/settings/NotificationTypeTexts.vue'
import { notificationTextsService } from '@/services/notificationTextsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/notificationTextsService', () => ({
  notificationTextsService: {
    show: vi.fn(),
    save: vi.fn(),
    reset: vi.fn(),
    updateChannels: vi.fn(),
  },
}))

function textOf(key, variant, audience) {
  const title = { ar: 'عنوان :entity', en: 'Title :entity' }
  return {
    key,
    variant,
    audience,
    variables: ['entity'],
    requiredVariables: [],
    isCustomized: false,
    updatedAt: null,
    parts: { title: { default: title, current: title, channel: 'in_app', isFixed: false } },
    sms: { current: null, approval: null },
  }
}

function settingsOf(enabled = true) {
  return ['beneficiary', 'management'].flatMap((audience) =>
    ['in_app', 'email', 'sms'].map((channel) => ({
      channel,
      audience,
      isEnabled: channel === 'in_app' && enabled,
      isShipped: channel !== 'sms',
      isSwitchable: true,
    })),
  )
}

const TYPE = {
  code: 'REG-01',
  category: 'info',
  isMandatory: false,
  isCore: false,
  audiences: ['entity', 'system_admin'],
  audienceKinds: ['beneficiary', 'management'],
  channels: ['in_app', 'email'],
  channelSettings: settingsOf(),
  texts: [
    textOf('entities.REG-01.entity', 'entity', 'beneficiary'),
    textOf('entities.REG-01.staff', 'staff', 'management'),
  ],
}

let wrapper

async function mountPage(code = 'REG-01') {
  wrapper = mount(NotificationTypeTexts, {
    props: { code },
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

function audienceTabs() {
  return wrapper.findAll('.notification-texts__audiences > .tabs__list [role="tab"]')
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('NotificationTypeTexts page', () => {
  it('shows the read-only recipients and a tab per audience text', async () => {
    notificationTextsService.show.mockResolvedValue({ data: TYPE })
    await mountPage()
    expect(notificationTextsService.show).toHaveBeenCalledWith('REG-01')
    expect(wrapper.find('.notification-recipients').text()).toContain('Beneficiary')
    expect(wrapper.find('.notification-recipients').text()).toContain('System Administrator')
    expect(audienceTabs().map((tab) => tab.text())).toEqual(['To the beneficiary', 'To management'])
    expect(wrapper.find('textarea[name="entities.REG-01.entity-title-ar"]').exists()).toBe(true)
    await audienceTabs()[1].trigger('click')
    expect(wrapper.find('textarea[name="entities.REG-01.staff-title-ar"]').exists()).toBe(true)
  })

  it('switches a channel off for the open audience and shows the saved state', async () => {
    notificationTextsService.show.mockResolvedValue({ data: TYPE })
    notificationTextsService.updateChannels.mockResolvedValue({
      data: { ...TYPE, channelSettings: settingsOf(false) },
    })
    await mountPage()
    await wrapper.find('input[role="switch"]').setValue(false)
    await flushPromises()
    expect(notificationTextsService.updateChannels).toHaveBeenCalledWith(
      'REG-01',
      { in_app: false },
      'beneficiary',
    )
    expect(wrapper.find('input[role="switch"]').element.checked).toBe(false)
  })

  it('marks a core notification and locks its switches', async () => {
    notificationTextsService.show.mockResolvedValue({
      data: { ...TYPE, isCore: true, texts: [TYPE.texts[0]] },
    })
    await mountPage()
    expect(wrapper.find('.notification-texts__core-banner').text()).toContain(
      'cannot be turned off',
    )
    expect(wrapper.find('input[role="switch"]').attributes('disabled')).toBeDefined()
  })

  it('points the verification code to the SMS settings for its text message', async () => {
    notificationTextsService.show.mockResolvedValue({
      data: { ...TYPE, code: 'REG-02', isCore: true, texts: [TYPE.texts[0]] },
    })
    await mountPage('REG-02')
    const pointer = wrapper.find('.notification-texts__pointer a')
    expect(pointer.text()).toBe('The verification code text message is edited in the SMS settings')
    expect(pointer.attributes('href')).toBe('/admin/settings/sms')
  })

  it('shows no SMS pointer for other notifications', async () => {
    notificationTextsService.show.mockResolvedValue({ data: TYPE })
    await mountPage()
    expect(wrapper.find('.notification-texts__pointer').exists()).toBe(false)
  })

  it('explains an unknown type', async () => {
    notificationTextsService.show.mockRejectedValue(
      Object.assign(new Error('missing'), { normalized: { status: 404 } }),
    )
    await mountPage('XYZ-99')
    expect(wrapper.text()).toContain('Notification not found')
  })
})
