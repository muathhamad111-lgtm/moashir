import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AccreditedSubmitDialog from '@/components/accredited/AccreditedSubmitDialog.vue'
import { createTestGlobals } from '@/test-utils'

function factory(props, locale = 'ar') {
  return mount(AccreditedSubmitDialog, {
    props: { modelValue: true, number: 'ACC-2026-000004', ...props },
    global: { ...createTestGlobals({ locale }), stubs: { teleport: true } },
    attachTo: document.body,
  })
}

describe('AccreditedSubmitDialog (J-1, Q-164)', () => {
  it('shows the period read from today as its one line', () => {
    const wrapper = factory({ period: 'الربع الرابع 2026' })
    expect(wrapper.find('[role="alertdialog"]').text()).toContain('إرسال الطلب للمراجعة؟')
    expect(wrapper.find('.modal__message').text()).toBe('الفترة: الربع الرابع 2026')
    wrapper.unmount()
  })

  it('turns into a warning with the next submission date when the period is assessed', async () => {
    const wrapper = factory({ blockedUntil: '2027-01-01' }, 'en')
    expect(wrapper.find('[role="alertdialog"]').text()).toContain('Submission opens on')
    expect(wrapper.find('[role="alertdialog"]').text()).toContain('2027-01-01')
    const buttons = wrapper.findAll('.modal__footer button, .modal button.btn')
    const ok = buttons.find((button) => button.text() === 'OK')
    await ok.trigger('click')
    expect(wrapper.emitted('confirm')).toBeUndefined()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    wrapper.unmount()
  })
})
