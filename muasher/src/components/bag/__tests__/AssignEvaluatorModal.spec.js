import { describe, expect, it, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AssignEvaluatorModal from '@/components/bag/AssignEvaluatorModal.vue'
import { createTestGlobals } from '@/test-utils'

function mountModal(props = {}) {
  return mount(AssignEvaluatorModal, {
    props: { modelValue: true, options: [{ value: 10, label: 'Sultan' }], ...props },
    attachTo: document.body,
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true, SearchSelect: true } },
  })
}

function confirmButton(wrapper) {
  return wrapper.findAll('button').find((b) => ['Assign', 'Reassign'].includes(b.text()))
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('AssignEvaluatorModal', () => {
  it('shows a reason field only in reassign mode', () => {
    const assign = mountModal({ mode: 'assign', currentEvaluatorId: 10 })
    expect(assign.find('textarea').exists()).toBe(false)
    assign.unmount()

    const reassign = mountModal({ mode: 'reassign', currentEvaluatorId: 10 })
    expect(reassign.find('textarea').exists()).toBe(true)
  })

  it('blocks a reassign without a reason and shows the inline error (§10/§16)', async () => {
    const wrapper = mountModal({ mode: 'reassign', currentEvaluatorId: 10 })
    await confirmButton(wrapper).trigger('click')
    expect(wrapper.emitted('confirm')).toBeFalsy()
    expect(wrapper.text()).toContain('A reassignment reason is required')
  })

  it('emits the reason on a valid reassign', async () => {
    const wrapper = mountModal({ mode: 'reassign', currentEvaluatorId: 10 })
    await wrapper.find('textarea').setValue('Original evaluator is on leave')
    await confirmButton(wrapper).trigger('click')
    expect(wrapper.emitted('confirm')[0][0]).toEqual({
      evaluatorId: 10,
      reason: 'Original evaluator is on leave',
    })
  })

  it('emits an assignment (no reason) when an evaluator is chosen', async () => {
    const wrapper = mountModal({ mode: 'assign', currentEvaluatorId: 10 })
    await confirmButton(wrapper).trigger('click')
    expect(wrapper.emitted('confirm')[0][0]).toEqual({ evaluatorId: 10, reason: '' })
  })

  it('blocks with a choose-error when no evaluator is selected', async () => {
    const wrapper = mountModal({ mode: 'assign', currentEvaluatorId: null })
    await confirmButton(wrapper).trigger('click')
    expect(wrapper.emitted('confirm')).toBeFalsy()
    expect(wrapper.text()).toContain('Choose an evaluator to continue')
  })
})
