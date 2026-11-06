import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { defineComponent, h } from 'vue'
import { useForm } from 'vee-validate'
import MethodChoice from '@/components/payments/MethodChoice.vue'
import ProofUpload from '@/components/payments/ProofUpload.vue'
import TransferDetails from '@/components/payments/TransferDetails.vue'
import TransferStatusPanel from '@/components/payments/TransferStatusPanel.vue'
import TransferSubmitFailure from '@/components/payments/TransferSubmitFailure.vue'
import TransferProofChip from '@/components/payments/TransferProofChip.vue'
import PaymentChannelCell from '@/components/payments/portal/PaymentChannelCell.vue'
import FieldIban from '@/components/form/FieldIban.vue'
import PaymentChannelsCard from '@/components/settings/payment/PaymentChannelsCard.vue'
import { usePaymentChannelsSettings } from '@/composables/usePaymentChannelsSettings'
import { paymentChannelsService } from '@/services/paymentChannelsService'
import { PROOF_STATES } from '@/constants/bankTransfer'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/paymentChannelsService', () => ({
  paymentChannelsService: { getChannels: vi.fn(), updateChannels: vi.fn() },
}))

const ACCOUNT = {
  id: 'acc-1',
  bankName: { ar: 'بنك المثال (مثال)', en: 'Example Bank (example)' },
  accountHolderName: { ar: 'منصة مؤشر — حساب الرسوم', en: 'Moashir Platform — fees account' },
  iban: 'SA0380000000608010167519',
  payerNote: null,
}

function globalsFor(locale = 'en') {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  return globals
}

function mountWith(component, props, locale = 'en') {
  return mount(component, { props, global: globalsFor(locale) })
}

function transferPayment(status, overrides = {}) {
  return {
    id: 'p-1',
    reference: 'PAY-2026-000024',
    purpose: 'accredited',
    channel: 'bank_transfer',
    status,
    pricing: { total: '1150.00', currency: 'SAR' },
    rejectedAt: null,
    bankTransfer: {
      bankAccount: ACCOUNT,
      proof: {
        id: 'f-1',
        name: 'bank-transfer-receipt.pdf',
        mimeType: 'application/pdf',
        sizeBytes: 524288,
      },
      submittedAt: '2026-11-01T09:10:00Z',
      reviewedAt: null,
      rejectionReason: null,
      reviewNote: null,
    },
    ...overrides,
  }
}

describe('MethodChoice', () => {
  it('offers the available channels as a radio group and continues with the chosen one', async () => {
    const wrapper = mountWith(MethodChoice, {
      channels: ['electronic', 'bank_transfer'],
      modelValue: 'bank_transfer',
    })
    expect(wrapper.find('[role="radiogroup"]').exists()).toBe(true)
    expect(wrapper.findAll('.method-option__title').map((title) => title.text())).toEqual([
      'Electronic payment',
      'Bank transfer',
    ])
    await wrapper.find('.method-option[data-value="electronic"] input').setValue(true)
    expect(wrapper.emitted('update:modelValue')).toEqual([['electronic']])
    await wrapper.find('.checkout__actions .btn--primary').trigger('click')
    expect(wrapper.emitted('continue')).toEqual([['bank_transfer']])
  })
})

describe('ProofUpload', () => {
  const file = new File(['%PDF'], 'bank-transfer-receipt.pdf', { type: 'application/pdf' })

  it('shows the dropzone, then the file with its progress, scanning and refusal states', async () => {
    const wrapper = mountWith(ProofUpload, {
      file: null,
      state: PROOF_STATES.EMPTY,
      labelledby: 'x',
    })
    expect(wrapper.find('.proof-upload__dropzone').text()).toContain('up to 20 MB')
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    expect(wrapper.emitted('select')).toEqual([[file]])
    await wrapper.setProps({ file, state: PROOF_STATES.UPLOADING, progress: 64 })
    expect(wrapper.find('progress').attributes('value')).toBe('64')
    expect(wrapper.find('.proof-chip').attributes('aria-busy')).toBe('true')
    await wrapper.setProps({ state: PROOF_STATES.SCANNING })
    expect(wrapper.find('.badge').text()).toBe('Scanning')
    expect(wrapper.find('.proof-chip button').exists()).toBe(false)
    await wrapper.setProps({ state: PROOF_STATES.UNSAFE })
    expect(wrapper.find('.proof-chip').classes()).toContain('is-refused')
    expect(wrapper.text()).toContain('could not be accepted for security reasons')
    await wrapper.setProps({ state: PROOF_STATES.FAILED })
    await wrapper.find('.proof-upload__retry .btn').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })

  it('accepts a dropped file', async () => {
    const wrapper = mountWith(ProofUpload, {
      file: null,
      state: PROOF_STATES.EMPTY,
      labelledby: 'x',
    })
    await wrapper
      .find('.proof-upload__dropzone')
      .trigger('drop', { dataTransfer: { files: [file] } })
    expect(wrapper.emitted('select')).toEqual([[file]])
  })
})

describe('TransferDetails', () => {
  it('shows the full grouped IBAN marked as an example, the amount and the unified number to copy', async () => {
    const writeText = vi.fn().mockResolvedValue()
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    const wrapper = mountWith(
      TransferDetails,
      { account: ACCOUNT, amount: { total: '1150.00' }, unifiedNumber: '7100005001' },
      'ar',
    )
    const rows = wrapper.findAll('.copy-row')
    expect(rows.map((row) => row.find('.copy-row__label').text())).toEqual([
      'البنك',
      'اسم صاحب الحساب',
      'رقم الآيبان (مثال)',
      'المبلغ المطلوب (ر.س)',
      'الرقم الوطني الموحد لجهتك',
    ])
    expect(rows[2].find('.copy-row__value').text()).toBe('SA03 8000 0000 6080 1016 7519')
    await rows[2].find('button').trigger('click')
    await flushPromises()
    expect(writeText).toHaveBeenCalledWith('SA0380000000608010167519')
    expect(wrapper.find('.transfer-details__guidance').text()).toContain(
      'الرقم الوطني الموحد لجهتك',
    )
  })
})

describe('TransferStatusPanel', () => {
  it('shows the review progress while Finance reviews', () => {
    const wrapper = mountWith(
      TransferStatusPanel,
      { payment: transferPayment('under-review') },
      'ar',
    )
    expect(wrapper.find('.transfer-status__title').text()).toBe('قيد مراجعة المالية')
    expect(wrapper.find('[aria-current="step"]').text()).toContain('مراجعة المالية')
  })

  it('shows the rejection reason, the note from the Finance team and a new request', () => {
    const wrapper = mountWith(
      TransferStatusPanel,
      {
        payment: transferPayment('rejected', {
          rejectedAt: '2026-11-02T07:05:00Z',
          bankTransfer: {
            ...transferPayment('rejected').bankTransfer,
            reviewedAt: '2026-11-02T07:05:00Z',
            rejectionReason: 'not_received',
            reviewNote: 'لم يصل المبلغ إلى الحساب حتى الآن.',
          },
        }),
      },
      'ar',
    )
    expect(wrapper.find('.transfer-status__reason').text()).toBe(
      'السبب: لم يظهر التحويل في كشف الحساب',
    )
    expect(wrapper.find('.transfer-status__note bdi').text()).toBe(
      'لم يصل المبلغ إلى الحساب حتى الآن.',
    )
    expect(wrapper.find('.transfer-status__by').text()).toContain('فريق المالية')
    expect(wrapper.find('a.btn--primary').attributes('href')).toBe('/assessment/accredited/pay')
    expect(wrapper.find('.transfer-progress').exists()).toBe(false)
  })
})

describe('TransferSubmitFailure', () => {
  it('sends an open card order to its completion page and a frozen entity to support', () => {
    const order = mountWith(TransferSubmitFailure, {
      failure: {
        kind: 'open-order',
        meta: { paymentId: 'p-7', channel: 'electronic' },
        message: '',
      },
    })
    expect(order.text()).toContain('You have an open payment order')
    expect(order.find('a').attributes('href')).toBe('/payments/p-7/complete')
    const blocked = mountWith(TransferSubmitFailure, {
      failure: { kind: 'blocked', meta: {}, message: 'The entity account is deleted.' },
    })
    expect(blocked.text()).toContain('Payment cannot be completed')
  })
})

describe('PaymentChannelCell', () => {
  it('names the channel and the card network for electronic payments', () => {
    const card = mountWith(PaymentChannelCell, {
      payment: { channel: 'electronic', method: 'mada' },
    })
    expect(card.text()).toContain('Electronic')
    expect(card.find('.payment-channel__method').exists()).toBe(true)
    const transfer = mountWith(PaymentChannelCell, { payment: { channel: 'bank_transfer' } })
    expect(transfer.text()).toBe('Bank transfer')
  })
})

describe('FieldIban', () => {
  it('formats the IBAN in groups of four while typing', async () => {
    const Host = defineComponent({
      setup() {
        useForm({ initialValues: { iban: '' } })
        return () => h(FieldIban, { name: 'iban', label: 'IBAN' })
      },
    })
    const wrapper = mount(Host, { global: globalsFor() })
    await wrapper.find('input').setValue('sa0380000000608010167519')
    await flushPromises()
    expect(wrapper.find('input').element.value).toBe('SA03 8000 0000 6080 1016 7519')
    expect(wrapper.find('input').attributes('dir')).toBe('ltr')
  })
})

describe('PaymentChannelsCard', () => {
  it('blocks saving bank transfer without an active account and offers to add one', async () => {
    paymentChannelsService.getChannels.mockResolvedValue({
      data: {
        channels: ['electronic'],
        availableChannels: ['electronic'],
        gatewayReady: true,
        activeBankAccountsCount: 0,
        reviewersCount: 0,
      },
    })
    let channels
    const Host = defineComponent({
      setup() {
        channels = usePaymentChannelsSettings({ reasonExtra: () => Promise.resolve({}) })
        return () => h(PaymentChannelsCard, { channels, gatewayAnchor: 'payment-gateway' })
      },
    })
    const wrapper = mount(Host, { global: globalsFor() })
    await channels.load()
    await flushPromises()
    await wrapper.find('.method-option[data-value="bank_transfer"] input').setValue(true)
    expect(wrapper.find('.channels-card__alert--danger').text()).toContain(
      'Bank transfer cannot be saved',
    )
    expect(wrapper.find('.channels-card__alert--warning').text()).toContain('No user can review')
    expect(wrapper.find('.channels-card__actions .btn').attributes('disabled')).toBeDefined()
    await wrapper.find('.channels-card__alert--danger .btn').trigger('click')
    expect(wrapper.findComponent(PaymentChannelsCard).emitted('add-account')).toHaveLength(1)
  })
})

describe('TransferProofChip', () => {
  it('keeps the sent date left-to-right inside the Arabic detail line', () => {
    const wrapper = mountWith(
      TransferProofChip,
      {
        payment: {
          id: 'pay-1',
          status: 'rejected',
          bankTransfer: {
            submittedAt: '2026-11-01T07:05:00Z',
            bankAccount: { bankName: ACCOUNT.bankName },
            proof: { name: 'bank-transfer-receipt.pdf', sizeBytes: 524288 },
          },
        },
      },
      'ar',
    )
    const date = wrapper.find('.proof-chip__detail bdi[dir="ltr"]')
    expect(date.text()).toBe('2026-11-01')
    expect(wrapper.find('.proof-chip__detail').text()).toContain('بنك المثال (مثال)')
  })
})
