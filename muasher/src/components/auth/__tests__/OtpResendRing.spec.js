import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OtpResendRing from '@/components/auth/OtpResendRing.vue'

function offsetOf(wrapper) {
  return Number(wrapper.find('.otp-resend-ring__fill').attributes('stroke-dashoffset'))
}

describe('OtpResendRing', () => {
  it('is decorative and starts full', () => {
    const wrapper = mount(OtpResendRing, { props: { secondsLeft: 60 } })
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(offsetOf(wrapper)).toBe(0)
  })

  it('drains with the seconds left', async () => {
    const wrapper = mount(OtpResendRing, { props: { secondsLeft: 60 } })
    await wrapper.setProps({ secondsLeft: 45 })
    expect(offsetOf(wrapper)).toBe(25)
    await wrapper.setProps({ secondsLeft: 0 })
    expect(offsetOf(wrapper)).toBe(100)
  })

  it('starts over when a new cooldown begins', async () => {
    const wrapper = mount(OtpResendRing, { props: { secondsLeft: 60 } })
    await wrapper.setProps({ secondsLeft: 0 })
    await wrapper.setProps({ secondsLeft: 30 })
    expect(offsetOf(wrapper)).toBe(0)
    await wrapper.setProps({ secondsLeft: 15 })
    expect(offsetOf(wrapper)).toBe(50)
  })

  it('shows an empty ring when the cooldown is already over', () => {
    expect(offsetOf(mount(OtpResendRing, { props: { secondsLeft: 0 } }))).toBe(100)
  })
})
