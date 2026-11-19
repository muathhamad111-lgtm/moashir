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

function textOf(key, variant) {
  return {
    key,
    variant,
    variables: ['entity'],
    requiredVariables: [],
    isCustomized: false,
    updatedAt: null,
    parts: {
      title: {
        default: { ar: 'عنوان :entity', en: 'Title :entity' },
        current: { ar: 'عنوان :entity', en: 'Title :entity' },
      },
    },
  }
}

const TYPE = {
  code: 'REG-01',
  category: 'info',
  isMandatory: false,
  channels: ['in_app', 'email'],
  channelSettings: [
    { channel: 'in_app', isEnabled: true, isShipped: true, isSwitchable: true },
    { channel: 'email', isEnabled: true, isShipped: true, isSwitchable: true },
    { channel: 'sms', isEnabled: false, isShipped: false, isSwitchable: false },
  ],
  texts: [textOf('entities.REG-01.entity', 'entity'), textOf('entities.REG-01.staff', 'staff')],
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

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('NotificationTypeTexts page', () => {
  it('shows the type, its channels and a tab per text variant', async () => {
    notificationTextsService.show.mockResolvedValue({ data: TYPE })
    await mountPage()
    expect(notificationTextsService.show).toHaveBeenCalledWith('REG-01')
    expect(wrapper.find('.notification-texts__meta').text()).toContain('REG-01')
    expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual([
      'To the entity',
      'To the platform team',
    ])
    expect(wrapper.find('textarea[name="entities.REG-01.entity-title-en"]').exists()).toBe(true)
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(wrapper.find('textarea[name="entities.REG-01.staff-title-en"]').exists()).toBe(true)
    expect(wrapper.find('.settings-section__back').text()).toContain('Back to notification types')
  })

  it('shows one editor without tabs when the type has one text', async () => {
    notificationTextsService.show.mockResolvedValue({ data: { ...TYPE, texts: [TYPE.texts[0]] } })
    await mountPage()
    expect(wrapper.find('[role="tab"]').exists()).toBe(false)
    expect(wrapper.find('.notification-text').exists()).toBe(true)
  })

  it('switches a channel off and shows the saved state', async () => {
    notificationTextsService.show.mockResolvedValue({ data: TYPE })
    notificationTextsService.updateChannels.mockResolvedValue({
      data: {
        ...TYPE,
        channelSettings: TYPE.channelSettings.map((row) =>
          row.channel === 'email' ? { ...row, isEnabled: false } : row,
        ),
      },
    })
    await mountPage()
    await wrapper.find('input[name="channel-email"]').setValue(false)
    await flushPromises()
    expect(notificationTextsService.updateChannels).toHaveBeenCalledWith('REG-01', { email: false })
    expect(wrapper.find('input[name="channel-email"]').element.checked).toBe(false)
  })

  it('explains an unknown type', async () => {
    notificationTextsService.show.mockRejectedValue(
      Object.assign(new Error('missing'), { normalized: { status: 404 } }),
    )
    await mountPage('XYZ-99')
    expect(wrapper.text()).toContain('Notification type not found')
  })
})
