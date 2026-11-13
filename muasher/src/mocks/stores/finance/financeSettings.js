import { ORDER_VALIDITY_LIMITS } from '../../fixtures/finance'
import { financeState, persistFinanceState } from './financeState'
import { actorCan, FINANCE_PERMISSIONS } from './financeAccess'
import { fieldMessage, financeReject } from './financeRejection'
import { normalizedRate, quoteOf } from './financeMoney'
import { priceInForce } from './priceVersions'

const VAT_PATTERN = /^\d{1,3}(\.\d{1,2})?$/
const VAT_NUMBER_PATTERN = /^3\d{13}3$/

const READ_PERMISSIONS = [
  FINANCE_PERMISSIONS.ASSESSMENT_SETTINGS,
  FINANCE_PERMISSIONS.BAG_ADMIN,
  FINANCE_PERMISSIONS.PAYMENT_SETTINGS,
]

const MOVED_PRICE_FIELDS = ['accreditedPrice', 'bagPrice']

export const FINANCE_FIELDS = {
  vatRate: { wire: 'vat_rate', permission: FINANCE_PERMISSIONS.ASSESSMENT_SETTINGS },
  orderValidityMinutes: {
    wire: 'order_validity_minutes',
    permission: FINANCE_PERMISSIONS.ASSESSMENT_SETTINGS,
  },
  vatRegistrationNumber: {
    wire: 'vat_registration_number',
    permission: FINANCE_PERMISSIONS.PAYMENT_SETTINGS,
  },
}

export function authorizeFinanceSettings(user) {
  if (!READ_PERMISSIONS.some((permission) => actorCan(user, permission)))
    throw financeReject.forbidden()
}

function editableFields(user) {
  return Object.values(FINANCE_FIELDS)
    .filter((field) => actorCan(user, field.permission))
    .map((field) => field.wire)
}

function previewOf(price, vatRate) {
  return price ? quoteOf(price, vatRate) : null
}

export function financeSettingsResource(user) {
  const settings = financeState().settings
  const accreditedPrice = priceInForce('accredited')
  const bagPrice = priceInForce('bag')
  return {
    currency: 'SAR',
    accreditedPrice,
    bagPrice,
    vatRate: settings.vatRate,
    orderValidityMinutes: settings.orderValidityMinutes,
    orderValidityLimits: { ...ORDER_VALIDITY_LIMITS },
    vatRegistrationNumber: settings.vatRegistrationNumber ?? null,
    vatRegistrationReminder: Number(settings.vatRate) > 0 && !settings.vatRegistrationNumber,
    pricing: {
      accredited: previewOf(accreditedPrice, settings.vatRate),
      bag: previewOf(bagPrice, settings.vatRate),
    },
    editableFields: editableFields(user),
  }
}

function vatError(value) {
  if (typeof value !== 'string' || !VAT_PATTERN.test(value) || Number(value) > 100)
    return fieldMessage('vat_format')
  return null
}

function vatNumberError(value) {
  if (value === null || value === '') return null
  return typeof value === 'string' && VAT_NUMBER_PATTERN.test(value)
    ? null
    : fieldMessage('vat_registration_number_format')
}

function validityError(value) {
  const { min, max } = ORDER_VALIDITY_LIMITS
  if (!Number.isInteger(value) || value < min || value > max)
    return fieldMessage('between', { min, max })
  return null
}

function reasonError(value) {
  if (value === undefined || value === null || value === '') return null
  const reason = String(value).trim()
  if (reason.length < 10) return fieldMessage('min', { min: 10 })
  if (reason.length > 1000) return fieldMessage('max', { max: 1000 })
  return null
}

const FIELD_CHECKS = {
  vatRate: vatError,
  orderValidityMinutes: validityError,
  vatRegistrationNumber: vatNumberError,
}

function presentFields(body) {
  return Object.keys(FINANCE_FIELDS).filter((field) => Object.hasOwn(body, field))
}

function movedErrors(body) {
  return MOVED_PRICE_FIELDS.filter((field) => Object.hasOwn(body, field)).map((field) => [
    field,
    fieldMessage('price_moved'),
  ])
}

function validated(body) {
  const errors = presentFields(body)
    .map((field) => [field, FIELD_CHECKS[field](body[field])])
    .concat(movedErrors(body))
    .concat([['reason', reasonError(body.reason)]])
    .filter(([, message]) => message)
  if (errors.length)
    throw financeReject.validation(
      Object.fromEntries(errors.map(([field, message]) => [field, [message]])),
    )
}

function storedValue(field, value) {
  if (field === 'vatRate') return normalizedRate(value)
  if (field === 'vatRegistrationNumber') return value || null
  return value
}

export function updateFinanceSettings(user, body) {
  const fields = presentFields(body)
  if (fields.some((field) => !actorCan(user, FINANCE_FIELDS[field].permission)))
    throw financeReject.forbidden()
  validated(body)
  if (!fields.length) return
  if (user.superAdmin && !String(body.reason ?? '').trim()) throw financeReject.reasonRequired()
  const settings = financeState().settings
  fields.forEach((field) => {
    settings[field] = storedValue(field, body[field])
  })
  persistFinanceState()
}
