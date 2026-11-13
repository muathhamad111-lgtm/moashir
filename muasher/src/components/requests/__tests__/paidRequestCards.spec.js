import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestPaymentCard from '../RequestPaymentCard.vue'
import PreparingRequestsTable from '../PreparingRequestsTable.vue'
import RequestFacts from '../RequestFacts.vue'
import LinkedRequestCard from '@/components/finance/LinkedRequestCard.vue'

function render(component, props, roles = ['system_admin'], locale = 'en') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  return mount(component, { props, global: { plugins: globals.plugins } })
}

const PAYMENT = {
  reference: 'PAY-2026-000012',
  channel: 'electronic',
  status: 'paid',
  isTest: false,
  paidAt: '2026-09-03T07:24:00Z',
  receipt: { number: 'RCP-2026-000004', status: 'issued', isTest: false },
}

const LINKED = {
  number: 'ACC-2026-000009',
  path: 'accredited',
  pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  status: 'draft',
  progress: { complete: 3, total: 19 },
}

describe('RequestPaymentCard', () => {
  it('links the payment record and the receipt download for finance holders', async () => {
    const payment = {
      ...PAYMENT,
      id: 'pay-12',
      receipt: { ...PAYMENT.receipt, id: 'rcp-4' },
    }
    const wrapper = render(RequestPaymentCard, { payment })
    expect(wrapper.text()).toContain('PAY-2026-000012')
    expect(wrapper.text()).toContain('RCP-2026-000004')
    expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual([
      '/finance/transactions/pay-12',
      '/finance/transactions/pay-12',
    ])
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('download')).toEqual([['rcp-4']])
  })

  it('shows the numbers as text without links to a follower', () => {
    const wrapper = render(RequestPaymentCard, { payment: PAYMENT }, ['request_follower'])
    expect(wrapper.text()).toContain('PAY-2026-000012')
    expect(wrapper.findAll('a')).toHaveLength(0)
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('names an unnumbered receipt with the neutral line in Arabic', () => {
    const payment = { ...PAYMENT, receipt: { number: null, status: 'issued', isTest: true } }
    const wrapper = render(RequestPaymentCard, { payment }, ['request_follower'], 'ar')
    expect(wrapper.text()).toContain('غير معتمد كإثبات دفع')
    expect(wrapper.text()).not.toContain('اختبار')
    expect(wrapper.text()).toContain('الدفع')
  })
})

describe('RequestFacts payment flag', () => {
  it('keeps the bare paid flag only when the payment card is not shown', () => {
    const base = {
      path: 'accredited',
      status: 'assigned',
      entity: { name: { en: 'Namaa' }, reference: 'ENT-006001' },
      assessor: null,
    }
    const assessorView = render(RequestFacts, {
      detail: { ...base, payment: { paid: true, details: null } },
    })
    const staffView = render(RequestFacts, {
      detail: { ...base, payment: { paid: true, details: PAYMENT } },
    })
    expect(assessorView.text()).toContain('Fee')
    expect(staffView.text()).not.toContain('Fee')
  })
})

describe('PreparingRequestsTable', () => {
  it('shows the payment, progress and open link of each paid draft', () => {
    const item = {
      id: 9101,
      number: 'ACC-2026-000004',
      path: 'accredited',
      pathLabel: LINKED.pathLabel,
      status: 'draft',
      entity: {
        name: { en: 'Namaa Development Foundation' },
        reference: 'ENT-006001',
        level: null,
      },
      openedAt: '2026-09-03T07:26:00Z',
      payment: PAYMENT,
      progress: { complete: 18, total: 19 },
      lastActivityAt: '2026-09-03T07:40:00Z',
    }
    const wrapper = render(PreparingRequestsTable, { items: [item], status: 'success' })
    expect(wrapper.text()).toContain('Namaa Development Foundation')
    expect(wrapper.text()).toContain('PAY-2026-000012')
    expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('18')
    expect(wrapper.find('a[href="/admin/requests/9101"]').exists()).toBe(true)
  })

  it('shows the empty state', () => {
    const wrapper = render(PreparingRequestsTable, { items: [], status: 'empty' })
    expect(wrapper.text()).toContain('No requests waiting for the entity')
  })
})

describe('LinkedRequestCard', () => {
  it('shows Finance the number, status and progress with a lock note and no link', () => {
    const wrapper = render(
      LinkedRequestCard,
      { linkedRequest: LINKED, paymentStatus: 'paid', entityId: 5001 },
      ['finance_officer'],
    )
    expect(wrapper.text()).toContain('ACC-2026-000009')
    expect(wrapper.text()).toContain('You see its number, status and progress only')
    expect(wrapper.find('a[href="/admin/requests/9"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/entities/5001"]').exists()).toBe(true)
  })

  it('links the request and the entity for the System Administrator', () => {
    const wrapper = render(LinkedRequestCard, {
      linkedRequest: { ...LINKED, id: 9 },
      paymentStatus: 'paid',
      entityId: 5001,
    })
    expect(wrapper.find('a[href="/admin/requests/9"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('You see its number, status and progress only')
  })

  it('explains that the request opens on approval while the transfer is under review', () => {
    const wrapper = render(
      LinkedRequestCard,
      { linkedRequest: null, paymentStatus: 'under_review', entityId: null },
      ['finance_officer'],
    )
    expect(wrapper.text()).toContain('opens automatically when the transfer is approved')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })
})
