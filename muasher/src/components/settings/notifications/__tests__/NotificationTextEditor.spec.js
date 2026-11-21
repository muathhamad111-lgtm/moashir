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

function textOf(overrides = {}) {
  return {
    key: 'staff_invited',
    variant: 'staff_invited',
    variables: ['duration'],
    requiredVariables: ['duration'],
    isCustomized: false,
    updatedAt: null,
    parts: {
      subject: {
        default: { ar: 'دعوة', en: 'Invitation' },
        current: { ar: 'دعوة', en: 'Invitation' },
      },
      expiry: {
        default: { ar: 'صالحة لمدة :duration', en: 'Valid for :duration' },
        current: { ar: 'صالحة لمدة :duration', en: 'Valid for :duration' },
      },
    },
    ...overrides,
  }
}

let wrapper

function mountEditor(text = textOf(), smsEnabled = false) {
  wrapper = mount(NotificationTextEditor, {
    props: { text, smsEnabled },
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  return wrapper
}

beforeEach(() => {
  notificationTextsService.save.mockResolvedValue({ data: { code: 'USR-01', texts: [] } })
  notificationTextsService.reset.mockResolvedValue(null)
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  vi.clearAllMocks()
})

describe('NotificationTextEditor', () => {
  it('shows each part in Arabic and English with the part name and the shipped badge', () => {
    mountEditor()
    expect(wrapper.findAll('legend').map((legend) => legend.text())).toEqual([
      'E-mail subject',
      'Validity line',
    ])
    expect(wrapper.find('textarea[name="staff_invited-subject-ar"]').attributes('dir')).toBe('rtl')
    expect(wrapper.find('textarea[name="staff_invited-subject-en"]').attributes('dir')).toBe('ltr')
    expect(wrapper.text()).toContain('Original text')
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('inserts a variable at the caret of the last focused field with a separating space', async () => {
    mountEditor()
    const field = wrapper.find('textarea[name="staff_invited-subject-en"]')
    await field.trigger('focusin')
    field.element.setSelectionRange(10, 10)
    const chip = wrapper.find('.notification-variables__chip')
    expect(chip.classes()).toContain('is-required')
    expect(chip.attributes('aria-label')).toBe('Insert Validity (:duration), required')
    expect(chip.text()).toContain('Validity')
    await chip.trigger('click')
    expect(field.element.value).toBe('Invitation :duration')
  })

  it('saves the edited parts and reports the type back', async () => {
    mountEditor()
    await wrapper.find('textarea[name="staff_invited-subject-ar"]').setValue('دعوتك إلى الفريق')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(notificationTextsService.save).toHaveBeenCalledWith('staff_invited', {
      subject: { ar: 'دعوتك إلى الفريق', en: 'Invitation' },
      expiry: { ar: 'صالحة لمدة :duration', en: 'Valid for :duration' },
    })
    expect(wrapper.emitted('saved')[0][0]).toEqual({ code: 'USR-01', texts: [] })
  })

  it('shows a refused variable under its field', async () => {
    notificationTextsService.save.mockRejectedValue(
      Object.assign(new Error('invalid'), {
        normalized: {
          status: 422,
          fieldErrors: { 'parts.expiry.ar': ['Keep the variable duration.'] },
        },
      }),
    )
    mountEditor()
    await wrapper.find('textarea[name="staff_invited-expiry-ar"]').setValue('صالحة لمدة قصيرة')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Keep the variable duration.')
    expect(
      wrapper.find('textarea[name="staff_invited-expiry-ar"]').attributes('aria-invalid'),
    ).toBe('true')
  })

  it('restores the original text only after confirmation', async () => {
    mountEditor(textOf({ isCustomized: true }))
    expect(wrapper.text()).toContain('Edited text')
    const reset = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Restore the original text')
    confirmMock.mockResolvedValueOnce(false)
    await reset.trigger('click')
    await flushPromises()
    expect(notificationTextsService.reset).not.toHaveBeenCalled()
    confirmMock.mockResolvedValueOnce(true)
    await reset.trigger('click')
    await flushPromises()
    expect(notificationTextsService.reset).toHaveBeenCalledWith('staff_invited')
    expect(wrapper.emitted('reset')).toHaveLength(1)
  })

  it('adds the text message part when SMS is on and shows its approval per language', async () => {
    mountEditor(textOf({ sms: { current: null, approval: null } }), true)
    expect(wrapper.findAll('legend').map((legend) => legend.text())).toContain('Text message')
    expect(wrapper.text()).toContain('then save it to see its approval')
    await wrapper.setProps({
      text: textOf({
        sms: {
          current: { ar: 'دعوة :duration', en: 'Invite :duration' },
          approval: { ar: 'approved', en: 'pending' },
        },
      }),
    })
    expect(wrapper.find('textarea[name="staff_invited-sms-ar"]').element.value).toBe(
      'دعوة :duration',
    )
    expect(wrapper.text()).toContain('Arabic — Approved in Msegat')
    expect(wrapper.text()).toContain('English — Awaiting Msegat approval')
  })

  it('leaves the text message out while SMS is off and none was written', () => {
    mountEditor(textOf({ sms: { current: null, approval: null } }), false)
    expect(wrapper.find('textarea[name="staff_invited-sms-ar"]').exists()).toBe(false)
  })
})
