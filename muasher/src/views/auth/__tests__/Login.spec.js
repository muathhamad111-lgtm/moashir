import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Login from '@/views/auth/Login.vue'
import { authService } from '@/services/authService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: {
    getLoginModes: vi.fn(() => Promise.resolve({ data: { emailPassword: true, emailOtp: true, smsOtp: false } })),
  },
}))

describe('Login view', () => {
  it('renders the enabled login modes from /auth/login-modes', async () => {
    const wrapper = mount(Login, { global: createTestGlobals({ locale: 'en' }) })
    await flushPromises()
    expect(authService.getLoginModes).toHaveBeenCalled()
    expect(wrapper.findAll('.auth-form__mode')).toHaveLength(2)
  })

  it('renders no demo login unless the local-only build flag is set', async () => {
    expect(import.meta.env.VITE_DEMO_LOGIN).toBeUndefined()
    const wrapper = mount(Login, { global: createTestGlobals({ locale: 'en' }) })
    await flushPromises()
    expect(wrapper.find('.auth-form__demo').exists()).toBe(false)
  })
})
