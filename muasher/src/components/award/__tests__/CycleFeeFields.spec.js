import { describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import CycleFeeFields from '@/components/award/CycleFeeFields.vue'
import { createTestGlobals } from '@/test-utils'

function factory(initialValues, locked = new Set(), locale = 'ar') {
  const Host = defineComponent({
    setup() {
      useForm({ initialValues })
      return () => h(CycleFeeFields, { locked })
    },
  })
  return mount(Host, { global: createTestGlobals({ locale }) })
}

describe('CycleFeeFields', () => {
  it('asks only for the amount before VAT once the cycle is paid', async () => {
    const free = factory({ entryFeePaid: false, entryFee: '' })
    expect(free.find('input[name="entryFee"]').exists()).toBe(false)
    expect(free.text()).toContain('الدورة مجانية: لا رسوم دخول.')
    await free.find('input[name="entryFeePaid"]').setValue(true)
    await nextTick()
    expect(free.find('input[name="entryFee"]').exists()).toBe(true)
    expect(free.find('.pricing-breakdown').exists()).toBe(false)
    expect(free.text()).not.toContain('0 يعني')
  })

  it('shows a paid fee in English with its value', () => {
    const paid = factory({ entryFeePaid: true, entryFee: '750.00' }, new Set(), 'en')
    expect(paid.find('input[name="entryFee"]').element.value).toBe('750.00')
    expect(paid.text()).toContain('Paid')
  })

  it('keeps a locked fee read only with the reason once applications open', () => {
    const locked = factory({ entryFeePaid: true, entryFee: '500.00' }, new Set(['entryFee']))
    expect(locked.find('input[name="entryFeePaid"]').attributes('disabled')).toBeDefined()
    expect(locked.find('input[name="entryFee"]').attributes('disabled')).toBeDefined()
    expect(locked.find('[data-testid="cycle-fee-locked"]').text()).toBe(
      'لا تُعدَّل الرسوم بعد فتح نافذة التقديم.',
    )
  })
})
