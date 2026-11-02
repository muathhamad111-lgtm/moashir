import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { useOtpChallenge } from '@/composables/useOtpChallenge'

function withOtp() {
  let api
  const Harness = defineComponent({
    setup() {
      api = useOtpChallenge()
      return () => null
    },
  })
  const wrapper = mount(Harness)
  return { api, wrapper }
}

function challenge(overrides = {}) {
  const now = Date.now()
  return {
    challengeId: '900',
    channel: 'email',
    length: 6,
    expiresAt: new Date(now + 120000).toISOString(),
    resendAvailableAt: new Date(now + 30000).toISOString(),
    ...overrides,
  }
}

beforeEach(() => {
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useOtpChallenge', () => {
  it('exposes the challenge length + channel', () => {
    const { api, wrapper } = withOtp()
    api.setChallenge(challenge())
    expect(api.length.value).toBe(6)
    expect(api.channel.value).toBe('email')
    expect(api.challengeId.value).toBe('900')
    wrapper.unmount()
  })

  it('counts the expiry down and flips to expired at zero', () => {
    const { api, wrapper } = withOtp()
    api.setChallenge(challenge())
    expect(api.expirySecondsLeft.value).toBeGreaterThan(0)
    expect(api.expired.value).toBe(false)
    vi.advanceTimersByTime(121000)
    expect(api.expirySecondsLeft.value).toBe(0)
    expect(api.expired.value).toBe(true)
    wrapper.unmount()
  })

  it('gates resend until the cooldown elapses', () => {
    const { api, wrapper } = withOtp()
    api.setChallenge(challenge())
    expect(api.canResend.value).toBe(false)
    expect(api.resendSecondsLeft.value).toBeGreaterThan(0)
    vi.advanceTimersByTime(31000)
    expect(api.canResend.value).toBe(true)
    expect(api.resendSecondsLeft.value).toBe(0)
    wrapper.unmount()
  })

  it('formats the expiry clock as m:ss', () => {
    const { api, wrapper } = withOtp()
    api.setChallenge(challenge({ expiresAt: new Date(Date.now() + 65000).toISOString() }))
    expect(api.expiryClock.value).toBe('1:05')
    wrapper.unmount()
  })
})
