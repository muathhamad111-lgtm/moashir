import {
  FINANCE_YEAR,
  abandonedCartsSeed,
  financeEntitiesSeed,
  financeSettingsSeed,
  paymentEventsSeed,
  paymentsSeed,
  providerPaymentsSeed,
  refundsSeed,
  storedFilesSeed,
} from '../../fixtures/finance'
import {
  bankAccountsSeed,
  bankTransferPaymentsSeed,
  transferProofFilesSeed,
  transfersSeed,
} from '../../fixtures/bankTransfers'
import { priceVersionsSeed } from '../../fixtures/priceVersions'
import { issueSeedReceipts } from './receiptSeed'

export const SEEDED_CHANNELS = Object.freeze(['electronic', 'bank_transfer'])

export const FINANCE_STORAGE_KEY = 'moashir_mock_finance'

const PERSISTED = import.meta.env.MODE !== 'test'

let state = null

function seededState() {
  const payments = [...paymentsSeed(), ...bankTransferPaymentsSeed()].map((payment) => ({
    ...payment,
    entityName: { ...financeEntitiesSeed[payment.entityId] },
  }))
  const refunds = refundsSeed()
  const { receipts, issued } = issueSeedReceipts(payments, refunds)
  return {
    payments,
    receipts,
    refunds,
    files: [...storedFilesSeed(), ...transferProofFilesSeed()],
    channels: [...SEEDED_CHANNELS],
    bankAccounts: bankAccountsSeed(),
    transfers: transfersSeed(),
    exports: [],
    events: paymentEventsSeed(),
    providerPayments: providerPaymentsSeed(),
    carts: abandonedCartsSeed(),
    settings: { ...financeSettingsSeed },
    priceVersions: priceVersionsSeed(),
    counters: {
      payment: { [FINANCE_YEAR]: payments.length },
      receipt: { [FINANCE_YEAR]: issued },
      event: paymentEventsSeed().length,
      export: 0,
      priceVersion: priceVersionsSeed().length,
    },
  }
}

function storage() {
  return PERSISTED && typeof sessionStorage !== 'undefined' ? sessionStorage : null
}

function restored() {
  const raw = storage()?.getItem(FINANCE_STORAGE_KEY)
  if (!raw) return null
  try {
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed?.bankAccounts) && Array.isArray(parsed?.priceVersions)
      ? parsed
      : null
  } catch {
    return null
  }
}

export function resetFinanceState() {
  state = seededState()
  storage()?.removeItem(FINANCE_STORAGE_KEY)
}

export function financeState() {
  if (!state) state = restored() ?? seededState()
  return state
}

export function persistFinanceState() {
  storage()?.setItem(FINANCE_STORAGE_KEY, JSON.stringify(financeState()))
}

export function nextSequence(counter, year = FINANCE_YEAR) {
  const counters = financeState().counters[counter]
  counters[year] = (counters[year] ?? 0) + 1
  return counters[year]
}

export function nextEventId() {
  const counters = financeState().counters
  counters.event += 1
  return counters.event
}

export function findPayment(id) {
  return financeState().payments.find((payment) => payment.id === id) ?? null
}

export function receiptOf(paymentId) {
  return financeState().receipts.find((receipt) => receipt.paymentId === paymentId) ?? null
}

export function findReceipt(id) {
  return financeState().receipts.find((receipt) => receipt.id === id) ?? null
}

export function refundOf(paymentId) {
  return financeState().refunds.find((refund) => refund.paymentId === paymentId) ?? null
}

export function findRefund(id) {
  return financeState().refunds.find((refund) => refund.id === id) ?? null
}

export function findFile(id) {
  return financeState().files.find((file) => file.id === id) ?? null
}

export function findBankAccount(id) {
  return financeState().bankAccounts.find((account) => account.id === id) ?? null
}

export function transferOf(paymentId) {
  return financeState().transfers.find((transfer) => transfer.paymentId === paymentId) ?? null
}

export function findExport(id) {
  return financeState().exports.find((item) => item.id === id) ?? null
}
