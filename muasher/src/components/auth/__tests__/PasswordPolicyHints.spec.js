import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PasswordPolicyHints from '@/components/auth/PasswordPolicyHints.vue'
import { createTestGlobals } from '@/test-utils'

function mountHints(props, locale = 'en') {
  return mount(PasswordPolicyHints, {
    props: { id: 'policy', ...props },
    global: createTestGlobals({ locale }),
  })
}

function stateOf(wrapper, rule) {
  return wrapper.find(`[data-rule="${rule}"]`).attributes('data-state')
}

describe('PasswordPolicyHints', () => {
  it('lists the rules as plain hints when it does not follow a password', () => {
    const wrapper = mountHints({ withHistory: true })
    expect(wrapper.classes()).not.toContain('password-policy--live')
    expect(wrapper.findAll('li').map((item) => item.attributes('data-rule'))).toEqual([
      'length',
      'breached',
      'history',
      'unique',
    ])
    expect(wrapper.findAll('[data-state="info"]')).toHaveLength(4)
    expect(wrapper.find('.password-policy__state').exists()).toBe(false)
  })

  it('ticks the length and match rules as the password is typed', async () => {
    const wrapper = mountHints({ password: '', confirmation: '' })
    expect(wrapper.classes()).toContain('password-policy--live')
    expect(stateOf(wrapper, 'length')).toBe('pending')
    expect(stateOf(wrapper, 'match')).toBe('pending')
    expect(stateOf(wrapper, 'breached')).toBe('info')
    expect(wrapper.find('[data-rule="length"] .password-policy__state').text()).toBe('Not met yet:')

    await wrapper.setProps({ password: 'Wafa-Charity-2026', confirmation: 'Wafa-Charity' })
    expect(stateOf(wrapper, 'length')).toBe('met')
    expect(stateOf(wrapper, 'match')).toBe('pending')
    expect(wrapper.find('[data-rule="length"] .password-policy__state').text()).toBe('Met:')

    await wrapper.setProps({ confirmation: 'Wafa-Charity-2026' })
    expect(stateOf(wrapper, 'match')).toBe('met')
  })

  it('keeps the length rule open past the maximum and words the rules in Arabic', () => {
    const wrapper = mountHints({ password: 'a'.repeat(129), confirmation: 'a'.repeat(129) }, 'ar')
    expect(stateOf(wrapper, 'length')).toBe('pending')
    expect(wrapper.find('.password-policy__title').text()).toBe('شروط كلمة المرور')
    expect(wrapper.find('[data-rule="match"]').text()).toContain(
      'أن تتطابق كلمة المرور مع تأكيدها.',
    )
  })
})
