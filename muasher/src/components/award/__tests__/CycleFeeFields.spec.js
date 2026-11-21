import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import CycleFeeFields from '@/components/award/CycleFeeFields.vue'
import { createTestGlobals } from '@/test-utils'

function factory(props) {
  const Host = defineComponent({
    setup() {
      useForm({ initialValues: { entryFee: '500.00' } })
      return () => h(CycleFeeFields, props)
    },
  })
  return mount(Host, { global: createTestGlobals({ locale: 'ar' }) })
}

describe('CycleFeeFields', () => {
  it('lets a draft cycle edit its fee and locks it with the reason once applications open', () => {
    const editable = factory({ locked: new Set(), quote: null, isNew: false })
    expect(editable.find('input[name="entryFee"]').attributes('disabled')).toBeUndefined()
    expect(editable.find('[data-testid="cycle-fee-locked"]').exists()).toBe(false)
    const locked = factory({
      locked: new Set(['entryFee']),
      quote: {
        priceBeforeVat: '500.00',
        vatRate: '15.00',
        vatAmount: '75.00',
        total: '575.00',
        currency: 'SAR',
      },
      isNew: false,
    })
    expect(locked.find('input[name="entryFee"]').attributes('disabled')).toBeDefined()
    expect(locked.find('[data-testid="cycle-fee-locked"]').text()).toBe(
      'لا تُعدَّل الرسوم بعد فتح نافذة التقديم.',
    )
    expect(locked.find('.pricing-breakdown').text()).toContain('575.00')
  })
})
