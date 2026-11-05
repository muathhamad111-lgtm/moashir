import { object as yupObject, string as yupString } from 'yup'
import {
  BANK_ACCOUNT_RULES,
  OTHER_REJECTION_REASON,
  REJECTION_NOTE,
  REJECTION_REASONS,
  SUPER_ADMIN_REASON,
} from '@/constants/bankTransfer'
import { IBAN_ISSUES, ibanIssue } from '@/utils/payments/iban'

const IBAN_MESSAGE_KEYS = {
  [IBAN_ISSUES.REQUIRED]: 'validation.required',
  [IBAN_ISSUES.CHARACTERS]: 'settings.bankAccounts.iban.errors.format',
  [IBAN_ISSUES.COUNTRY]: 'settings.bankAccounts.iban.errors.country',
  [IBAN_ISSUES.LENGTH]: 'settings.bankAccounts.iban.errors.length',
  [IBAN_ISSUES.FORMAT]: 'settings.bankAccounts.iban.errors.format',
  [IBAN_ISSUES.CHECKSUM]: 'settings.bankAccounts.iban.errors.checksum',
}

function boundedText(t, { min, max, required = true }) {
  const base = yupString().trim()
  const sized = base
    .test('min', t('validation.minChars', { min }), (value) => !value || value.length >= min)
    .max(max, t('validation.maxChars', { max }))
  return required ? sized.required(t('validation.required')) : sized
}

function reasonField(t, asksReason) {
  if (!asksReason) return yupString().notRequired()
  return boundedText(t, { min: SUPER_ADMIN_REASON.MIN, max: SUPER_ADMIN_REASON.MAX })
}

function nameField(t) {
  return boundedText(t, { min: BANK_ACCOUNT_RULES.NAME_MIN, max: BANK_ACCOUNT_RULES.NAME_MAX })
}

function noteField(t, other) {
  return boundedText(t, { min: 1, max: BANK_ACCOUNT_RULES.NOTE_MAX, required: false }).test(
    'pair',
    t('settings.bankAccounts.fields.notePair'),
    (value, context) => Boolean(value?.trim()) || !context.parent?.[other]?.trim(),
  )
}

function payerNoteField(t) {
  return yupObject({ ar: noteField(t, 'en'), en: noteField(t, 'ar') })
}

export function ibanMessage(t, value) {
  const issue = ibanIssue(value)
  return issue ? t(IBAN_MESSAGE_KEYS[issue]) : null
}

function ibanField(t) {
  return yupString().test('iban', (value, context) => {
    const message = ibanMessage(t, value)
    return message ? context.createError({ message }) : true
  })
}

function accountNumberField(t) {
  return yupString()
    .trim()
    .test(
      'account-number',
      t('settings.bankAccounts.fields.accountNumberInvalid', {
        min: BANK_ACCOUNT_RULES.ACCOUNT_NUMBER_MIN,
        max: BANK_ACCOUNT_RULES.ACCOUNT_NUMBER_MAX,
      }),
      (value) => !value || BANK_ACCOUNT_RULES.ACCOUNT_NUMBER_PATTERN.test(value),
    )
}

export function bankAccountSchema(t) {
  return yupObject({
    bankName: yupObject({ ar: nameField(t), en: nameField(t) }),
    accountHolderName: yupObject({ ar: nameField(t), en: nameField(t) }),
    iban: ibanField(t),
    accountNumber: accountNumberField(t),
    payerNote: payerNoteField(t),
  })
}

export function payerNoteSchema(t) {
  return yupObject({ payerNote: payerNoteField(t) })
}

export function rejectionSchema(t, { asksReason = false } = {}) {
  return yupObject({
    rejectionReason: yupString()
      .required(t('finance.transfers.reject.reasonRequired'))
      .oneOf(REJECTION_REASONS, t('finance.transfers.reject.reasonRequired')),
    note: yupString()
      .trim()
      .when('rejectionReason', ([preset], schema) =>
        preset === OTHER_REJECTION_REASON
          ? schema.required(t('finance.transfers.reject.noteRequired'))
          : schema,
      )
      .test(
        'min',
        t('finance.transfers.reject.noteMin', { min: REJECTION_NOTE.MIN }),
        (value) => !value || value.length >= REJECTION_NOTE.MIN,
      )
      .max(REJECTION_NOTE.MAX, t('validation.maxChars', { max: REJECTION_NOTE.MAX })),
    reason: reasonField(t, asksReason),
  })
}

export function approvalSchema(t, { asksReason = false } = {}) {
  return yupObject({ reason: reasonField(t, asksReason) })
}

function bilingualPayload(value) {
  const ar = value?.ar?.trim() ?? ''
  const en = value?.en?.trim() ?? ''
  return ar || en ? { ar, en } : null
}

export function bankAccountPayload(values) {
  return {
    bankName: { ar: values.bankName.ar.trim(), en: values.bankName.en.trim() },
    accountHolderName: {
      ar: values.accountHolderName.ar.trim(),
      en: values.accountHolderName.en.trim(),
    },
    iban: String(values.iban ?? '')
      .replace(/\s+/g, '')
      .toUpperCase(),
    ...(values.accountNumber?.trim() ? { accountNumber: values.accountNumber.trim() } : {}),
    ...(bilingualPayload(values.payerNote)
      ? { payerNote: bilingualPayload(values.payerNote) }
      : {}),
  }
}

export function payerNotePayload(values) {
  return bilingualPayload(values.payerNote)
}
