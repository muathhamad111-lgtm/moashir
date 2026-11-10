import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), captureException: vi.fn(), error: vi.fn() },
}))

let wrapper

function factory(props = {}, locale = 'ar') {
  const globals = createTestGlobals({ locale })
  wrapper = mount(ReasonDialog, {
    props: {
      modelValue: true,
      title: 'رفض تغيير البريد',
      subtitle: 'جمعية الأمل الخيرية',
      submitLabel: 'رفض',
      action: vi.fn().mockResolvedValue({ done: true }),
      actionName: 'entities.emailChanges.reject.submit',
      ...props,
    },
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

async function settle() {
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve))
  await flushPromises()
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('ReasonDialog', () => {
  it('names the action in the title and the object in the subtitle, with no extra paragraph', () => {
    factory()
    const panel = wrapper.find('.modal__panel')
    expect(wrapper.find('.modal__title').text()).toBe('رفض تغيير البريد')
    expect(wrapper.find('.modal__subtitle').text()).toBe('جمعية الأمل الخيرية')
    expect(panel.attributes('aria-describedby')).toBe(wrapper.find('.modal__subtitle').attributes('id'))
    expect(wrapper.find('.reason-dialog').findAll('p:not(.field__hint):not(.field__error)')).toHaveLength(0)
  })

  it('puts the action first and cancel second, with the danger style when destructive', () => {
    factory({ destructive: true })
    const buttons = wrapper.findAll('.modal__footer .btn')
    expect(buttons.map((button) => button.text())).toEqual(['رفض', 'إلغاء'])
    expect(buttons[0].classes()).toContain('btn--danger')
  })

  it('keeps the primary style for a non-destructive reason', () => {
    factory({ submitLabel: 'حفظ' })
    expect(wrapper.find('.modal__footer .btn').classes()).toContain('btn--primary')
  })

  it('shows inline validation and does not call the action for a short reason', async () => {
    factory({}, 'en')
    await wrapper.find('textarea[name="reason"]').setValue('short')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(wrapper.find('.field__error').exists()).toBe(true))
    expect(wrapper.props('action')).not.toHaveBeenCalled()
    expect(wrapper.find('textarea').attributes('aria-invalid')).toBe('true')
  })

  it('sends the trimmed reason, closes and reports the result', async () => {
    factory()
    await wrapper.find('textarea[name="reason"]').setValue('  البريد الجديد لا يخص الجهة  ')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(wrapper.emitted('done')).toBeTruthy())
    expect(wrapper.props('action')).toHaveBeenCalledWith('البريد الجديد لا يخص الجهة')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    expect(wrapper.emitted('done')[0]).toEqual([{ done: true }])
  })

  it('disables the action while the request runs to block a double submit', async () => {
    let finish
    const action = vi.fn(() => new Promise((resolve) => (finish = resolve)))
    factory({ action })
    await wrapper.find('textarea[name="reason"]').setValue('البريد الجديد لا يخص الجهة')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(action).toHaveBeenCalledTimes(1))
    await flushPromises()
    const submit = wrapper.find('.modal__footer .btn--primary')
    expect(submit.attributes('disabled')).toBeDefined()
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(action).toHaveBeenCalledTimes(1)
    finish()
    await settle()
  })

  it('uses a custom hint and accepts an empty optional reason', async () => {
    factory({ optional: true, reasonHint: 'يصل السبب إلى الجهة' })
    expect(wrapper.text()).toContain('يصل السبب إلى الجهة')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(wrapper.props('action')).toHaveBeenCalledWith(''))
  })

  it('closes on Escape', async () => {
    factory()
    await wrapper.find('.modal').trigger('keydown', { key: 'Escape' })
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
  })
})
