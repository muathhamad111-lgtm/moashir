import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PaymentReceiptAction from '@/components/payments/portal/PaymentReceiptAction.vue'
import { createTestGlobals } from '@/test-utils'

const PAYMENT_ID = '7c2f4e1a-52b0-4c8e-9a31-00000000000a'

function mountAction(payment) {
  const globals = createTestGlobals({ locale: 'ar' })
  const wrapper = mount(PaymentReceiptAction, { props: { payment }, global: globals })
  return { wrapper, router: globals.router }
}

const REJECTED = {
  id: PAYMENT_ID,
  reference: 'PAY-2026-000009',
  status: 'rejected',
  channel: 'bank_transfer',
  purpose: 'accredited',
}

describe('payment row action', () => {
  it('offers a rejected transfer two icons: the reason and details, and a new request', () => {
    const { wrapper, router } = mountAction(REJECTED)
    const reason = wrapper.find('[data-action="rejection"]')
    const again = wrapper.find('[data-action="new-request"]')
    expect(reason.attributes('aria-label')).toBe('عرض سبب الرفض والتفاصيل PAY-2026-000009')
    expect(reason.attributes('title')).toBe('عرض سبب الرفض والتفاصيل')
    expect(reason.attributes('href')).toBe(
      router.resolve({ name: 'payments.mine.detail', params: { id: PAYMENT_ID } }).href,
    )
    expect(again.attributes('aria-label')).toBe('تقديم طلب دفع جديد PAY-2026-000009')
    expect(again.find('svg').attributes('data-icon')).toBe('refresh')
    expect(wrapper.text()).not.toContain('تقديم طلب دفع جديد')
  })

  it('keeps the labelled details link for a transfer under review', () => {
    const { wrapper } = mountAction({ ...REJECTED, status: 'under-review' })
    expect(wrapper.find('[data-action="rejection"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('عرض التفاصيل')
  })
})
