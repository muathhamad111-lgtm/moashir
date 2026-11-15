import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import RefundEvidenceField from '@/components/finance/RefundEvidenceField.vue'
import RefundRecord from '@/components/finance/RefundRecord.vue'
import TransactionFilters from '@/components/finance/TransactionFilters.vue'
import TransactionsTable from '@/components/finance/TransactionsTable.vue'
import TransactionEventsCard from '@/components/finance/TransactionEventsCard.vue'
import TransactionParties from '@/components/finance/TransactionParties.vue'
import TransactionFacts from '@/components/finance/TransactionFacts.vue'
import { financeService } from '@/services/financeService'
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
  const FILTERS = {
    type: '',
    status: '',
    purpose: '',
    from: '',
    to: '',
    entity_id: '',
    mode: '',
  }

  function comboboxes(wrapper) {
    return wrapper.findAllComponents({ name: 'AppCombobox' })
  }

  it('offers the operation type and the status as multi-select dropdowns', () => {
    const wrapper = mountWith(TransactionFilters, { filters: { ...FILTERS }, sort: '-created_at' })
    const [type, status] = comboboxes(wrapper)
    expect(type.props('label')).toBe('Operation type')
    expect(type.props('multiple')).toBe(true)
    expect(type.props('options').map((option) => option.value)).toEqual([
      'electronic',
      'bank_transfer',
      'refund',
    ])
    expect(status.props('label')).toBe('Status')
    expect(status.props('multiple')).toBe(true)
    expect(status.props('options')).toHaveLength(7)
    expect(wrapper.find('[name="transactions-from"]').exists()).toBe(true)
    expect(wrapper.find('[name="transactions-to"]').exists()).toBe(true)
  })

  it('lists the choices by name only; definitions stay on the table status', async () => {
    const wrapper = mountWith(TransactionFilters, { filters: { ...FILTERS }, sort: '-created_at' })
    const [type, status] = comboboxes(wrapper)
    expect(
      [...type.props('options'), ...status.props('options')].every(
        (option) => option.description === undefined,
      ),
    ).toBe(true)
    await status.find('input').trigger('keydown', { key: 'ArrowDown' })
    const failed = status
      .findAll('[role="option"]')
      .find((option) => option.text().includes('Payment failed'))
    expect(failed.text()).toBe('Payment failed')
  })

  it('offers actual payments by default and test payments on request', async () => {
    const wrapper = mountWith(TransactionFilters, { filters: { ...FILTERS }, sort: '-created_at' })
    const mode = wrapper
      .findAllComponents({ name: 'AppSelect' })
      .find((select) => select.props('label') === 'Environment')
    expect(mode.props('modelValue')).toBe('')
    expect(mode.props('options').map((option) => [option.value, option.label])).toEqual([
      ['', 'Live payments'],
      ['test', 'Test payments'],
    ])
    mode.vm.$emit('update:modelValue', 'test')
    expect(wrapper.emitted('filter')).toEqual([['mode', 'test']])
  })

  it('keeps the chosen values comma-separated in the catalogue order', async () => {
    const wrapper = mountWith(TransactionFilters, {
      filters: { ...FILTERS, type: 'refund', status: 'under_review' },
      sort: '-created_at',
    })
    const [type, status] = comboboxes(wrapper)
    expect(type.props('modelValue')).toEqual(['refund'])
    type.vm.$emit('update:modelValue', ['refund', 'electronic'])
    status.vm.$emit('update:modelValue', ['under_review', 'paid', 'unknown'])
    status.vm.$emit('update:modelValue', [])
    expect(wrapper.emitted('filter')).toEqual([
      ['type', 'electronic,refund'],
      ['status', 'paid,under_review'],
      ['status', ''],
    ])
  })
})

describe('TransactionsTable', () => {
  const ROW = {
    id: 'p-9',
    reference: 'PAY-2026-000009',
    receiptNumber: 'RCP-2026-000009',
    purpose: 'accredited',
    entity: { name: { ar: 'جمعية تجريبية', en: 'Sample Association' }, email: 'info@example.org' },
    channel: 'electronic',
    method: 'mada',
    status: 'paid',
    pricing: { priceBeforeVat: '1000.00', vatAmount: '150.00', total: '1150.00', currency: 'SAR' },
    updatedAt: '2026-11-10T09:00:00Z',
  }

  it('groups the service with its references and the channel with its method', () => {
    const wrapper = mountWith(TransactionsTable, { items: [ROW], status: 'success' })
    const headers = wrapper.findAll('th').map((header) => header.text())
    expect(headers.slice(0, 3)).toEqual(['Operation', 'Entity', 'Payment'])
    expect(headers).not.toContain('Payment reference')
    expect(headers).not.toContain('Payment method')
    const cells = wrapper.findAll('tbody td')
    expect(cells[0].text()).toContain('PAY-2026-000009')
    expect(cells[0].text()).toContain('Receipt RCP-2026-000009')
    const payment = cells[2].findAll('.transactions-table__stack > *')
    expect(payment).toHaveLength(2)
    expect(payment[1].text()).toBe('mada')
    const updated = wrapper.find('time.transactions-table__date').findAll('span')
    expect(updated.map((line) => line.text())).toEqual(['2026-11-10', '12:00 PM'])
  })

  it('explains the status when the pointer rests on it', async () => {
    const wrapper = mountWith(TransactionsTable, {
      items: [{ ...ROW, status: 'failed' }],
      status: 'success',
    })
    await wrapper.find('.transactions-table__status').trigger('mouseenter')
    await wrapper.find('.tooltip').trigger('mouseenter')
    expect(wrapper.find('[role="tooltip"]').text()).toContain('declined the operation')
  })

  it('opens the operation from an eye icon instead of a details word', () => {
    const wrapper = mountWith(TransactionsTable, { items: [ROW], status: 'success' })
    const view = wrapper.find('.transactions-table__view')
    expect(view.element.tagName).toBe('A')
    expect(view.attributes('href')).toContain('p-9')
    expect(view.attributes('aria-label')).toBe('View details — PAY-2026-000009')
    expect(view.find('.btn__label').exists()).toBe(false)
  })
})

describe('TransactionEventsCard', () => {
  const EVENT = {
    id: 41,
    createdAt: '2026-11-10T09:00:00Z',
    source: 'webhook',
    type: 'payment.paid',
    result: 'applied',
    mode: 'live',
    providerReference: '…a1b2c3',
  }

  it("lists the operation's events with their outcome and masked gateway reference", async () => {
    const list = vi
      .spyOn(financeService, 'listTransactionEvents')
      .mockResolvedValue({ data: [EVENT], meta: { limit: 50, hasMore: true } })
    const wrapper = mountWith(TransactionEventsCard, { paymentId: 'p-9' })
    await flushPromises()
    expect(list).toHaveBeenCalledWith('p-9')
    const items = wrapper.findAll('.transaction-events__item')
    expect(items).toHaveLength(1)
    expect(items[0].find('.badge').text()).toBe('Applied')
    expect(items[0].text()).toContain('…a1b2c3')
    expect(wrapper.text()).toContain('Only the latest 50 events are shown.')
    list.mockRestore()
  })

  it('says so when the operation has no events, and offers a retry on failure', async () => {
    const list = vi
      .spyOn(financeService, 'listTransactionEvents')
      .mockResolvedValueOnce({ data: [], meta: { limit: 50, hasMore: false } })
      .mockRejectedValueOnce({ normalized: { message: 'Unavailable' } })
    const empty = mountWith(TransactionEventsCard, { paymentId: 'p-1' })
    await flushPromises()
    expect(empty.text()).toContain('No events are recorded for this operation.')
    const failed = mountWith(TransactionEventsCard, { paymentId: 'p-2' })
    await flushPromises()
    expect(failed.findComponent({ name: 'ErrorState' }).exists()).toBe(true)
    list.mockRestore()
  })
})

describe('transaction receipt sections', () => {
  const PAYMENT = {
    id: 'p-11',
    purpose: 'bag',
    awardCycle: null,
    channel: 'electronic',
    method: 'mada',
    cardLast4: '4242',
    providerPaymentId: 'gw_000011',
    failureReason: null,
    entity: {
      reference: 'ENT-005001',
      name: { ar: 'جمعية تجريبية', en: 'Sample Association' },
      email: 'info@example.org',
    },
    createdAt: '2026-10-31T09:00:00Z',
  }

  it('opens with the service and the entity, without the entity e-mail', () => {
    const wrapper = mountWith(TransactionParties, { transaction: PAYMENT })
    expect(wrapper.findAll('dt').map((term) => term.text())).toEqual(['Service', 'Entity'])
    expect(wrapper.text()).toContain('Sample Association')
    expect(wrapper.text()).toContain('ENT-005001')
    expect(wrapper.text()).not.toContain('info@example.org')
  })

  it('keeps the payment details after the amounts, without repeating the parties', () => {
    const wrapper = mountWith(TransactionFacts, { transaction: PAYMENT })
    const terms = wrapper.findAll('dt').map((term) => term.text())
    expect(terms.slice(0, 2)).toEqual(['Payment channel', 'Payment method'])
    expect(terms).not.toContain('Entity')
    expect(terms).not.toContain('Service')
  })
})
