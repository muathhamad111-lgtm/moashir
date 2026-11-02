import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FormModal from '@/components/modal/FormModal.vue'
import { createTestGlobals } from '@/test-utils'

let wrapper

function factory(props = {}) {
  const globals = createTestGlobals()
  wrapper = mount(FormModal, {
    props: { modelValue: true, title: 'إضافة مستخدم', ...props },
    slots: { default: '<input name="name" />' },
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('FormModal', () => {
  it('hosts a form inside the modal and emits submit', async () => {
    factory()
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('submit')).toHaveLength(1)
  })

  it('shows a busy submit button that blocks double submits', () => {
    factory({ busy: true })
    const submit = document.querySelector('button[type="submit"]')
    expect(submit.disabled).toBe(true)
    expect(submit.classList.contains('btn--loading')).toBe(true)
    expect(wrapper.find('form').attributes('aria-busy')).toBe('true')
  })

  it('cancel button closes the modal', async () => {
    factory()
    const cancel = wrapper.findAll('.modal__footer .btn')[0]
    await cancel.trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
  })
})
