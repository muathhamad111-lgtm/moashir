import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ConfirmModal from '@/components/modal/ConfirmModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { createTestGlobals } from '@/test-utils'

let wrapper

function factory() {
  const globals = createTestGlobals()
  wrapper = mount(ConfirmModal, {
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('ConfirmModal + useConfirm', () => {
  it('opens with the message and resolves true on accept', async () => {
    factory()
    const { confirm } = useConfirm()
    const promise = confirm({ message: 'هل تريد تعطيل هذا المستخدم؟' })
    await wrapper.vm.$nextTick()
    expect(document.body.textContent).toContain('هل تريد تعطيل هذا المستخدم؟')
    const buttons = [...document.querySelectorAll('.modal__footer .btn')]
    buttons[1].click()
    await expect(promise).resolves.toBe(true)
  })

  it('resolves false on cancel and uses the danger variant when asked', async () => {
    factory()
    const { confirm } = useConfirm()
    const promise = confirm({ message: 'إجراء خطر', variant: 'danger' })
    await wrapper.vm.$nextTick()
    expect(document.querySelector('.modal__footer .btn--danger')).not.toBeNull()
    const buttons = [...document.querySelectorAll('.modal__footer .btn')]
    buttons[0].click()
    await expect(promise).resolves.toBe(false)
  })
})
