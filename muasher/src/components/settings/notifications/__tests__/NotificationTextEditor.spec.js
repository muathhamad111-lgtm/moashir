import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NotificationTextEditor from '@/components/settings/notifications/NotificationTextEditor.vue'
import { notificationTextsService } from '@/services/notificationTextsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/notificationTextsService', () => ({
  notificationTextsService: { save: vi.fn(), reset: vi.fn() },
}))

const confirmMock = vi.fn()
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: confirmMock }) }))

function part(ar, en, channel, isFixed = false) {
  return { default: { ar, en }, current: { ar, en }, channel, isFixed }
}

function registeredText(overrides = {}) {
  return {
    key: 'entities.REG-01.entity',
    variant: 'entity',
    audience: 'beneficiary',
    variables: ['entity'],
    requiredVariables: [],
    isCustomized: false,
    updatedAt: null,
    parts: {
      title: part('استلمنا تسجيل :entity', 'We received :entity', 'in_app'),
      body: part('سجّلت :entity في المنصة.', ':entity registered.', 'in_app'),
      emailSubject: part('استلمنا تسجيل :entity', 'We received :entity', 'email'),
      emailBody: part('سجّلت :entity في المنصة.', ':entity registered.', 'email'),
    },
    sms: { current: null, approval: null },
    ...overrides,
  }
}

function invitationText() {
  return {
    key: 'staff_invited',
    variant: 'staff_invited',
    audience: null,
    variables: ['duration'],
    requiredVariables: ['duration'],
    isCustomized: false,
    updatedAt: null,
    parts: {
      subject: part('دعوة', 'Invitation', 'email'),
      line: part('أنشأ المدير حسابك.', 'An account was created for you.', 'email'),
      action: part('قبول الدعوة', 'Accept the invitation', 'email', true),
      expiry: part('صالحة لمدة :duration', 'Valid for :duration', 'email'),
    },
    sms: { current: null, approval: null },
  }
}

const SETTINGS = [
  { channel: 'in_app', audience: 'beneficiary', isEnabled: true },
  { channel: 'email', audience: 'beneficiary', isEnabled: false },
  { channel: 'email', audience: 'management', isEnabled: true },
  { channel: 'sms', audience: 'beneficiary', isEnabled: false },
]

let wrapper

function mountEditor(props = {}) {
  wrapper = mount(NotificationTextEditor, {
    props: { text: registeredText(), channelSettings: SETTINGS, ...props },
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  return wrapper
}

function tabLabels() {
  return wrapper.findAll('.notification-text__channels [role="tab"]').map((tab) => tab.text())
}

async function openTab(index) {
  await wrapper.findAll('.notification-text__channels [role="tab"]')[index].trigger('click')
}

beforeEach(() => {
  notificationTextsService.save.mockResolvedValue({ data: { code: 'REG-01', texts: [] } })
  notificationTextsService.reset.mockResolvedValue(null)
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('NotificationTextEditor', () => {
  it('gives every channel its own tab, state and fields, starting on in-app', () => {
    mountEditor()
    expect(tabLabels()).toEqual(['In-app On', 'E-mail Off', 'Text message Off'])
    expect(wrapper.find('textarea[name="entities.REG-01.entity-title-ar"]').exists()).toBe(true)
    expect(wrapper.find('textarea[name="entities.REG-01.entity-emailSubject-ar"]').exists()).toBe(
      false,
    )
    expect(wrapper.find('input[role="switch"]').element.checked).toBe(true)
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('reads the channel state for the text audience only', async () => {
    mountEditor()
    await openTab(1)
    expect(wrapper.find('input[role="switch"]').element.checked).toBe(false)
    expect(wrapper.find('textarea[name="entities.REG-01.entity-emailSubject-ar"]').exists()).toBe(
      true,
    )
    expect(wrapper.find('.notification-format-bar').exists()).toBe(true)
  })

  it('switches the channel for the text audience', async () => {
    mountEditor()
    await openTab(1)
    await wrapper.find('input[role="switch"]').setValue(true)
    expect(wrapper.emitted('switch')[0][0]).toEqual({
      channel: 'email',
      enabled: true,
      audience: 'beneficiary',
    })
  })

  it('previews the e-mail live in the chosen language, with sample values', async () => {
    mountEditor()
    await openTab(1)
    const subject = wrapper.find('textarea[name="entities.REG-01.entity-emailSubject-ar"]')
    await subject.setValue('مرحبًا :entity')
    expect(wrapper.find('.notification-preview--email').text()).toContain(
      'مرحبًا جمعية النور الخيرية',
    )
    await wrapper.findAll('.notification-view-switch__option')[1].trigger('click')
    expect(wrapper.find('textarea[name="entities.REG-01.entity-emailSubject-en"]').exists()).toBe(
      true,
    )
    expect(wrapper.find('.notification-preview--email').attributes('dir')).toBe('ltr')
  })

  it('counts the characters and messages of the text message', async () => {
    mountEditor()
    await openTab(2)
    await wrapper.find('textarea[name="entities.REG-01.entity-sms-ar"]').setValue('ن'.repeat(71))
    expect(wrapper.find('.notification-sms-counter').text()).toBe('71/70 characters · messages: 2')
    expect(wrapper.find('.notification-sms-counter').classes()).toContain('is-long')
  })

  it('saves only the parts that differ from the shipped wording', async () => {
    mountEditor()
    await wrapper.find('textarea[name="entities.REG-01.entity-title-ar"]').setValue('عنوان جديد')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(notificationTextsService.save).toHaveBeenCalledWith('entities.REG-01.entity', {
      title: { ar: 'عنوان جديد', en: 'We received :entity' },
    })
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })

  it('shows the shipped state of a core notification, its fixed button and no text message', () => {
    mountEditor({
      text: invitationText(),
      isCore: true,
      channelSettings: [{ channel: 'email', audience: 'management', isEnabled: true }],
    })
    expect(tabLabels()).toEqual(['E-mail On'])
    expect(wrapper.find('input[role="switch"]').element.checked).toBe(true)
    expect(wrapper.find('textarea[name="staff_invited-action-ar"]').exists()).toBe(false)
    expect(wrapper.find('.notification-channel-fields__fixed').text()).toContain('قبول الدعوة')
    expect(wrapper.find('input[role="switch"]').attributes('disabled')).toBeDefined()
  })

  it('shows the server error under the field it belongs to', async () => {
    notificationTextsService.save.mockRejectedValue({
      normalized: { fieldErrors: { 'parts.expiry.ar': ['Keep the variable :duration.'] } },
    })
    mountEditor({ text: invitationText(), isCore: true })
    await wrapper.find('textarea[name="staff_invited-expiry-ar"]').setValue('صالحة لمدة قصيرة')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Keep the variable :duration.')
  })

  it('restores the original text after confirmation', async () => {
    confirmMock.mockResolvedValue(true)
    mountEditor({ text: registeredText({ isCustomized: true }) })
    expect(wrapper.text()).toContain('Edited')
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Restore'))
      .trigger('click')
    await flushPromises()
    expect(notificationTextsService.reset).toHaveBeenCalledWith('entities.REG-01.entity')
    expect(wrapper.emitted('reset')).toHaveLength(1)
  })
})
