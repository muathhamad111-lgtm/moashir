import { BANK_ACCOUNT_RULES, BANK_ACCOUNT_STATES } from '@/constants/bankTransfer'
import { ibanIssue, normalizeIban } from '@/utils/payments/iban'
import { usersFixture } from '../../fixtures/users'
import { financeState, persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { nowIso } from './paymentLifecycle'
import { pendingTransfersOn } from './bankTransferRecords'

export const MAX_ACTIVE_ACCOUNTS = 10
const PAYEE_FIELDS = ['bankName', 'accountHolderName', 'iban', 'accountNumber']
const LOCALES = ['ar', 'en']
const IBAN_FIELD_MESSAGES = {
  required: 'required',
  characters: 'iban_format',
  country: 'iban_country',
  length: 'iban_length',
  format: 'iban_format',
  checksum: 'iban_checksum',
}

function listedAccounts() {
  return financeState()
    .bankAccounts.filter((account) => account.archivedAt === null)
    .sort((left, right) => left.displayOrder - right.displayOrder)
}

export function usageOf(account) {
  const transfers = financeState().transfers.filter(
    (transfer) => transfer.bankAccountId === account.id,
  )
  return { isUsed: transfers.length > 0, pending: pendingTransfersOn(account.id).length }
}

export function bankAccountResource(account) {
  const usage = usageOf(account)
  return {
    id: account.id,
    bankName: { ...account.bankName },
    accountHolderName: { ...account.accountHolderName },
    iban: account.iban,
    accountNumber: account.accountNumber,
    payerNote: account.payerNote ? { ...account.payerNote } : null,
    displayOrder: account.displayOrder,
    isActive: account.isActive,
    isUsed: usage.isUsed,
    pendingTransfersCount: usage.pending,
    activatedAt: account.activatedAt,
    deactivatedAt: account.deactivatedAt,
    createdAt: account.createdAt,
    createdBy: { ...account.createdBy },
  }
}

export function payerAccountResource(account) {
  return {
    id: account.id,
    bankName: { ...account.bankName },
    accountHolderName: { ...account.accountHolderName },
    iban: account.iban,
    accountNumber: account.accountNumber,
    payerNote: account.payerNote ? { ...account.payerNote } : null,
  }
}

export function listBankAccounts(filters = {}) {
  const active = filters.active
  return listedAccounts().filter(
    (account) => active === undefined || active === '' || account.isActive === (active === '1'),
  )
}

export function selectableAccounts() {
  return listedAccounts().filter((account) => account.isActive)
}

function text(value) {
  return typeof value === 'string' ? value.trim() : ''
}

function lengthIssue(value, min, max) {
  if (!value) return fieldMessage('required')
  if (value.length < min) return fieldMessage('min', { min })
  if (value.length > max) return fieldMessage('max', { max })
  return null
}

function bilingualErrors(errors, field, value, { min, max, optional = false }) {
  if (optional && (value === null || value === undefined)) return
  LOCALES.forEach((locale) => {
    const issue = lengthIssue(text(value?.[locale]), min, max)
    if (issue) errors[`${field}.${locale}`] = [issue]
  })
}

function ibanErrors(errors, iban) {
  const issue = ibanIssue(iban)
  if (issue) {
    errors.iban = [fieldMessage(IBAN_FIELD_MESSAGES[issue])]
    return
  }
  const taken = financeState().bankAccounts.some((account) => account.iban === normalizeIban(iban))
  if (taken) errors.iban = [fieldMessage('iban_taken')]
}

function accountNumberErrors(errors, value) {
  if (value === null || value === undefined || value === '') return
  if (!BANK_ACCOUNT_RULES.ACCOUNT_NUMBER_PATTERN.test(String(value)))
    errors.accountNumber = [fieldMessage('invalid')]
}

function bilingual(value) {
  return value ? { ar: text(value.ar), en: text(value.en) } : null
}

export function validatedNewAccount(body) {
  const errors = {}
  const nameRules = { min: BANK_ACCOUNT_RULES.NAME_MIN, max: BANK_ACCOUNT_RULES.NAME_MAX }
  bilingualErrors(errors, 'bankName', body.bankName, nameRules)
  bilingualErrors(errors, 'accountHolderName', body.accountHolderName, nameRules)
  bilingualErrors(errors, 'payerNote', body.payerNote, {
    min: 1,
    max: BANK_ACCOUNT_RULES.NOTE_MAX,
    optional: true,
  })
  ibanErrors(errors, body.iban)
  accountNumberErrors(errors, body.accountNumber)
  if (Object.keys(errors).length) throw financeReject.validation(errors)
  return {
    bankName: bilingual(body.bankName),
    accountHolderName: bilingual(body.accountHolderName),
    iban: normalizeIban(body.iban),
    accountNumber: body.accountNumber ? String(body.accountNumber) : null,
    payerNote: bilingual(body.payerNote),
  }
}

function actorOf(user) {
  if (user.superAdmin) return { id: user.id, name: null }
  const staff = usersFixture.find((item) => item.id === user.id)
  return { id: user.id, name: staff?.name ?? null }
}

function assertBelowLimit() {
  if (selectableAccounts().length >= MAX_ACTIVE_ACCOUNTS)
    throw financeReject.accountLimitReached(MAX_ACTIVE_ACCOUNTS)
}

export function createBankAccount(user, fields) {
  assertBelowLimit()
  const at = nowIso()
  const order = Math.max(0, ...listedAccounts().map((account) => account.displayOrder)) + 1
  const account = {
    id: crypto.randomUUID(),
    ...fields,
    displayOrder: order,
    isActive: true,
    activatedAt: at,
    deactivatedAt: null,
    archivedAt: null,
    createdAt: at,
    createdBy: actorOf(user),
  }
  financeState().bankAccounts.push(account)
  persistFinanceState()
  return account
}

export function updateAccountNote(account, body) {
  if (PAYEE_FIELDS.some((field) => field in body)) throw financeReject.payeeImmutable()
  if (!('payerNote' in body))
    throw financeReject.validation({ payerNote: [fieldMessage('required')] })
  const errors = {}
  bilingualErrors(errors, 'payerNote', body.payerNote, {
    min: 1,
    max: BANK_ACCOUNT_RULES.NOTE_MAX,
    optional: true,
  })
  if (Object.keys(errors).length) throw financeReject.validation(errors)
  account.payerNote = bilingual(body.payerNote)
  persistFinanceState()
  return account
}

function bankTransferEnabled() {
  return financeState().channels.includes('bank_transfer')
}

export function assertMayRetire(account) {
  const pending = pendingTransfersOn(account.id)
  if (pending.length) throw financeReject.pendingTransfers(pending)
  const lastSelectable = account.isActive && selectableAccounts().length === 1
  if (lastSelectable && bankTransferEnabled()) throw financeReject.lastActiveAccount()
}

export function deactivateAccount(account) {
  if (!account.isActive) throw financeReject.illegalTransition('inactive', 'deactivate')
  assertMayRetire(account)
  account.isActive = false
  account.deactivatedAt = nowIso()
  persistFinanceState()
  return account
}

export function activateAccount(account) {
  if (account.isActive) throw financeReject.illegalTransition('active', 'activate')
  assertBelowLimit()
  account.isActive = true
  account.activatedAt = nowIso()
  account.deactivatedAt = null
  persistFinanceState()
  return account
}

export function deleteAccount(account) {
  assertMayRetire(account)
  const state = financeState()
  if (usageOf(account).isUsed) {
    const at = nowIso()
    account.isActive = false
    account.deactivatedAt = account.deactivatedAt ?? at
    account.archivedAt = at
  } else {
    state.bankAccounts = state.bankAccounts.filter((item) => item.id !== account.id)
  }
  persistFinanceState()
}

export function reorderAccounts(body) {
  const listed = listedAccounts()
  const ids = Array.isArray(body.orderedIds) ? body.orderedIds : []
  const complete =
    ids.length === listed.length &&
    new Set(ids).size === ids.length &&
    listed.every((account) => ids.includes(account.id))
  if (!complete) throw financeReject.validation({ orderedIds: [fieldMessage('invalid')] })
  ids.forEach((id, index) => {
    listed.find((account) => account.id === id).displayOrder = index + 1
  })
  persistFinanceState()
  return listedAccounts()
}

function stateOf(account) {
  if (account.archivedAt !== null) return BANK_ACCOUNT_STATES.ARCHIVED
  return account.isActive ? BANK_ACCOUNT_STATES.ACTIVE : BANK_ACCOUNT_STATES.INACTIVE
}

export function transferAccounts() {
  const archived = financeState()
    .bankAccounts.filter((account) => account.archivedAt !== null && usageOf(account).isUsed)
    .sort((left, right) => left.archivedAt.localeCompare(right.archivedAt))
  return [...listedAccounts(), ...archived]
}

export function transferAccountResource(account) {
  return {
    id: account.id,
    bankName: { ...account.bankName },
    ibanLast4: account.iban.slice(-4),
    state: stateOf(account),
  }
}

export function listedAccount(id) {
  return listedAccounts().find((account) => account.id === id) ?? null
}
