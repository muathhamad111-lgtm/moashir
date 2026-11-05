import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import FinanceNav from '@/components/finance/FinanceNav.vue'
import RefundEvidenceField from '@/components/finance/RefundEvidenceField.vue'
import RefundRecord from '@/components/finance/RefundRecord.vue'
import TransactionFilters from '@/components/finance/TransactionFilters.vue'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

function mountWith(component, props, { locale = 'en', roles = [], extra = {} } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(roles, extra))
  return mount(component, { props, global: globals })
}

const REFUND = {
  id: 'f-1',
  payment: { id: 'p-1', reference: 'PAY-2026-000003' },
  status: 'recorded',
  amount: '1150.00',
  currency: 'SAR',
  refundedOn: '2026-09-01',
  transferReference: 'TRF-2026-0091',
  reason: 'The entity withdrew before the assessor started.',
  recordedBy: { id: 22, name: 'سعود بن مبارك الدواسر' },
  evidence: {
    id: 'e-1',
    name: 'bank-transfer-receipt.pdf',
    mimeType: 'application/pdf',
    sizeBytes: 184320,
    scanStatus: 'clean',
  },
  createdAt: '2026-09-02T09:20:00Z',
}

describe('FinanceNav', () => {
  it('shows only the sections the permissions allow', () => {
    const officer = mountWith(FinanceNav, {}, { roles: ['finance_officer'] })
    expect(officer.findAll('.finance-nav__link').map((link) => link.text())).toEqual([
      'Summary',
      'Transactions',
      'Refunds',
    ])
    const admin = mountWith(FinanceNav, {}, { roles: ['system_admin'] })
    expect(admin.findAll('.finance-nav__link').map((link) => link.text())).toEqual([
      'Summary',
      'Transactions',
      'Refunds',
      'Payment events',
      'Abandoned carts',
      'Prices and VAT',
    ])
  })

  it('denies unknown permission keys and renders nothing for a single section', () => {
    const unknown = mountWith(
      FinanceNav,
      {},
      { extra: { permissions: ['finance.anything', 'finance.everything.manage'] } },
    )
    expect(unknown.find('.finance-nav').exists()).toBe(false)
    const single = mountWith(
      FinanceNav,
      {},
      { extra: { permissions: ['finance.anything', 'finance.refunds.manage'] } },
    )
    expect(single.find('.finance-nav').exists()).toBe(false)
  })
})

describe('RefundEvidenceField', () => {
  const file = new File(['%PDF-1.4'], 'bank-transfer-receipt.pdf', { type: 'application/pdf' })

  it.each([
    ['idle', 'Ready to upload', 'badge--muted'],
    ['pending', 'Uploading and scanning', 'badge--warning'],
    ['clean', 'Scanned, clean', 'badge--success'],
    ['refused', 'Refused', 'badge--danger'],
  ])('shows the %s scan state as text', (uploadState, label, variant) => {
    const wrapper = mountWith(RefundEvidenceField, { file, uploadState })
    expect(wrapper.find(`.refund-evidence__file .${variant}`).text()).toContain(label)
  })

  it('announces a refusal and lets the file be removed', async () => {
    const wrapper = mountWith(RefundEvidenceField, {
      file,
      uploadState: 'refused',
      refusal: 'The file was refused by the malware scan.',
    })
    expect(wrapper.find('.refund-evidence__refusal').attributes('role')).toBe('alert')
    await wrapper.find('.refund-evidence__file .btn').trigger('click')
    expect(wrapper.emitted('select')).toEqual([[null]])
  })
})

describe('RefundRecord', () => {
  it('shows the recorded refund and allows the evidence download only when the file is clean', async () => {
    const clean = mountWith(RefundRecord, { refund: REFUND }, { locale: 'ar' })
    expect(clean.text()).toContain('TRF-2026-0091')
    expect(clean.find('.badge--success').text()).toBe('سليم')
    await clean.find('.card__footer .btn').trigger('click')
    expect(clean.emitted('download')).toHaveLength(1)
    const pending = mountWith(RefundRecord, {
      refund: { ...REFUND, evidence: { ...REFUND.evidence, scanStatus: 'pending' } },
    })
    expect(pending.find('.card__footer .btn').attributes('disabled')).toBeDefined()
  })
})

describe('TransactionFilters', () => {
  it('emits the filter key and value chosen, and the sort', async () => {
    const wrapper = mountWith(TransactionFilters, {
      filters: { status: '', purpose: '', method: '', mode: '', from: '', to: '' },
      sort: '-created_at',
    })
    await wrapper.findAll('.dropdown__toggle')[3].trigger('click')
    await wrapper.findAll('.dropdown__item')[1].trigger('click')
    expect(wrapper.emitted('filter')).toEqual([['mode', 'test']])
    await wrapper.findAll('.dropdown__toggle')[4].trigger('click')
    await wrapper.findAll('.dropdown__item')[2].trigger('click')
    expect(wrapper.emitted('sort')).toEqual([['-total']])
  })
})
