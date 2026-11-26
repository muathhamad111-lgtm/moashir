import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import RefundEvidenceField from '@/components/finance/RefundEvidenceField.vue'
import RefundRecord from '@/components/finance/RefundRecord.vue'
import TransactionFilters from '@/components/finance/TransactionFilters.vue'
import TransactionsTable from '@/components/finance/TransactionsTable.vue'
import PaymentEventsTable from '@/components/finance/PaymentEventsTable.vue'
import TransactionEventsCard from '@/components/finance/TransactionEventsCard.vue'
import TransactionParties from '@/components/finance/TransactionParties.vue'
import TransactionFacts from '@/components/finance/TransactionFacts.vue'
import { financeService } from '@/services/financeService'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
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

  it('offers both modes by default and either one on request', async () => {
    const wrapper = mountWith(TransactionFilters, { filters: { ...FILTERS }, sort: '-created_at' })
    const mode = wrapper
      .findAllComponents({ name: 'AppSelect' })
      .find((select) => select.props('label') === 'Environment')
    expect(mode.props('modelValue')).toBe('')
    expect(mode.props('options').map((option) => [option.value, option.label])).toEqual([
      ['', 'All'],
      ['live', 'Live payments'],
      ['test', 'Preview payments'],
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

function viewOnly() {
  return [{ key: 'view', label: 'View details', icon: 'eye' }]
}

describe('TransactionsTable', () => {
  const ROW = {
    id: 'p-9',
    reference: 'PAY-2026-000009',
    receiptNumber: 'RCP-2026-000009',
    purpose: 'accredited',
    entity: { reference: 'ENT-00042', name: { ar: 'جمعية النماء الخيرية', en: 'Namaa Charity' } },
    channel: 'electronic',
    method: 'mada',
    status: 'paid',
    pricing: { priceBeforeVat: '1000.00', vatAmount: '150.00', total: '1150.00', currency: 'SAR' },
    createdAt: '2026-11-10T09:00:00Z',
  }

  it('shows the operation, the entity, the amount with its VAT, the status and the date by default', () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [ROW],
      status: 'success',
    })
    const headers = wrapper.findAll('th').map((header) => header.text())
    expect(headers).toEqual(['Operation', 'Entity', 'Amount', 'Status', 'Date', expect.any(String)])
    const cells = wrapper.findAll('tbody td')
    expect(cells[0].text()).toContain('PAY-2026-000009')
    expect(cells[0].text()).toContain('Accredited')
    expect(cells[2].text()).toContain('1,150.00')
    expect(cells[2].text()).toContain('150.00')
    const created = wrapper.find('time.transactions-table__date').findAll('span')
    expect(created.map((line) => line.text())).toEqual(['2026-11-10', '12:00 PM'])
  })

  it('shows the payment channel and method once the viewer turns that column on', async () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [ROW],
      status: 'success',
    })
    await flushPromises()
    useTableColumnsStore().choices = {
      'finance-transactions': ['entity', 'total', 'payment', 'status', 'createdAt'],
    }
    await flushPromises()
    const payment = wrapper.findAll('tbody td')[3].findAll('.transactions-table__stack > *')
    expect(payment).toHaveLength(2)
    expect(payment[1].text()).toBe('mada')
  })

  it('shows the award cycle and the refunded amount once the viewer chooses those columns', async () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [
        {
          ...ROW,
          awardCycle: { id: 4, name: { ar: 'دورة 2026', en: 'Cycle 2026' } },
          refund: { id: 'f-1', amount: '1150.00', currency: 'SAR' },
          refundedAt: '2026-11-12T09:00:00Z',
        },
      ],
      status: 'success',
    })
    await flushPromises()
    useTableColumnsStore().choices = { 'finance-transactions': ['awardCycle', 'refundAmount'] }
    await flushPromises()
    const cells = wrapper.findAll('tbody td')
    expect(cells[1].text()).toBe('Cycle 2026')
    expect(cells[2].text()).toContain('1,150.00')
  })

  it('identifies the entity by its initials and number, never by an e-mail', () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [{ ...ROW, entity: { ...ROW.entity, email: 'owner@namaa.sa' } }],
      status: 'success',
    })
    const entity = wrapper.findAll('tbody td')[1]
    expect(entity.find('.transactions-table__avatar').text()).toBe('NC')
    expect(entity.text()).toContain('ENT-00042')
    expect(wrapper.text()).not.toContain('owner@namaa.sa')
  })

  it('starts with the optional columns hidden and sorts by amount and date', async () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [ROW],
      status: 'success',
      sort: '-created_at',
    })
    const headers = wrapper.findAll('th').map((header) => header.text())
    expect(headers).not.toContain('Receipt no.')
    expect(headers).not.toContain('Payment')
    expect(wrapper.find('th[aria-sort="descending"]').text()).toBe('Date')
    await wrapper.findAll('.table__sort')[0].trigger('click')
    expect(wrapper.emitted('sort')).toEqual([['-total']])
  })

  it('explains the status when the pointer rests on it', async () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [{ ...ROW, status: 'failed' }],
      status: 'success',
    })
    await wrapper.find('.transactions-table__status').trigger('mouseenter')
    await wrapper.find('.tooltip').trigger('mouseenter')
    expect(wrapper.find('[role="tooltip"]').text()).toContain('declined the operation')
  })

  it('offers the row actions as icon buttons named after the operation', async () => {
    const wrapper = mountWith(TransactionsTable, {
      actionsFor: viewOnly,
      items: [ROW],
      status: 'success',
    })
    const view = wrapper.find('[data-action="view"]')
    expect(view.element.tagName).toBe('BUTTON')
    expect(view.attributes('aria-label')).toBe('View details — PAY-2026-000009')
    expect(view.find('.btn__label').exists()).toBe(false)
    await view.trigger('click')
    expect(wrapper.emitted('action')[0][0]).toEqual({ key: 'view', item: ROW })
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

describe('PaymentEventsTable', () => {
  const EVENT = {
    id: 'ev-1',
    payment: {
      id: 'p-9',
      reference: 'PAY-2026-000009',
      entityName: { ar: 'جمعية النماء الخيرية', en: 'Namaa Charity' },
    },
    source: 'webhook',
    eventType: 'payment_paid',
    mode: 'live',
    signatureValid: true,
    result: 'applied',
    errorCode: 'card_declined',
    providerRef: '…a1b2c3',
    createdAt: '2026-11-10T09:00:00Z',
    processedAt: '2026-11-10T09:00:02Z',
  }

  it('shows the entity of the payment and the error code once the viewer chooses them', async () => {
    const wrapper = mountWith(PaymentEventsTable, { items: [EVENT], status: 'success' })
    await flushPromises()
    useTableColumnsStore().choices = { 'finance-payment-events': ['entity', 'errorCode'] }
    await flushPromises()
    const cells = wrapper.findAll('tbody td')
    expect(cells[1].text()).toBe('Namaa Charity')
    expect(cells[2].text()).toBe('card_declined')
  })
})
