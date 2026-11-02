import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OtpInput from '@/components/form/OtpInput.vue'

function mountOtp(props = {}) {
  return mount(OtpInput, {
    attachTo: document.body,
    props: { modelValue: '', length: 6, label: 'Verification code', ...props },
  })
}

describe('OtpInput', () => {
  it('renders one box per length with a per-box accessible name', () => {
    const wrapper = mountOtp({ length: 4 })
    const boxes = wrapper.findAll('.otp-input__box')
    expect(boxes).toHaveLength(4)
    expect(boxes[0].attributes('aria-label')).toBe('Verification code — 1')
    expect(boxes[3].attributes('aria-label')).toBe('Verification code — 4')
    expect(wrapper.find('.otp-input').attributes('role')).toBe('group')
  })

  it('splits the modelValue across the boxes', () => {
    const wrapper = mountOtp({ modelValue: '12', length: 4 })
    const boxes = wrapper.findAll('.otp-input__box')
    expect(boxes[0].element.value).toBe('1')
    expect(boxes[1].element.value).toBe('2')
    expect(boxes[2].element.value).toBe('')
  })

  it('emits the assembled value when a digit is typed', async () => {
    const wrapper = mountOtp({ length: 4 })
    const boxes = wrapper.findAll('.otp-input__box')
    boxes[0].element.value = '7'
    await boxes[0].trigger('input')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['7'])
  })

  it('emits complete only when every box is filled', async () => {
    const wrapper = mountOtp({ modelValue: '123', length: 4 })
    const boxes = wrapper.findAll('.otp-input__box')
    boxes[3].element.value = '4'
    await boxes[3].trigger('input')
    const last = wrapper.emitted('update:modelValue').at(-1)
    expect(last).toEqual(['1234'])
    expect(wrapper.emitted('complete')[0]).toEqual(['1234'])
  })

  it('fills every box from a pasted code and strips non-digits', async () => {
    const wrapper = mountOtp({ length: 6 })
    const first = wrapper.findAll('.otp-input__box')[0]
    const clipboardData = { getData: () => '48-29-13' }
    await first.trigger('paste', { clipboardData })
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['482913'])
    expect(wrapper.emitted('complete').at(-1)).toEqual(['482913'])
  })

  it('clears the current digit on backspace and steps back when empty', async () => {
    const wrapper = mountOtp({ modelValue: '12', length: 4 })
    const boxes = wrapper.findAll('.otp-input__box')
    await boxes[2].trigger('keydown', { key: 'Backspace' })
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['1'])
  })

  it('marks boxes invalid when invalid is set', () => {
    const wrapper = mountOtp({ modelValue: '1', invalid: true })
    expect(wrapper.find('.otp-input__box').classes()).toContain('is-invalid')
  })

  it('disables every box when disabled', () => {
    const wrapper = mountOtp({ disabled: true })
    expect(wrapper.findAll('.otp-input__box')[0].attributes('disabled')).toBeDefined()
  })
})
