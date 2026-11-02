import { ORDER_VALIDITY_LIMITS } from '../../fixtures/finance'
import { financeState, persistFinanceState } from './financeState'
import { actorCan, FINANCE_PERMISSIONS } from './financeAccess'
import { fieldMessage, financeReject } from './financeRejection'
import { normalizedRate, quoteOf } from './financeMoney'

const PRICE_PATTERN = /^\d{1,7}\.\d{2}$/
const VAT_PATTERN = /^\d{1,3}(\.\d{1,2})?$/

export const FINANCE_FIELDS = {
  accreditedPrice: { wire: 'accredited_price', permission: FINANCE_PERMISSIONS.ASSESSMENT_SETTINGS },
  bagPrice: { wire: 'bag_price', permission: FINANCE_PERMISSIONS.BAG_ADMIN },
  vatRate: { wire: 'vat_rate', permission: FINANCE_PERMISSIONS.ASSESSMENT_SETTINGS },
  orderValidityMinutes: { wire: 'order_validity_minutes', permission: FINANCE_PERMISSIONS.ASSESSMENT_SETTINGS },
}

export function authorizeFinanceSettings(user) {
  if (!Object.values(FINANCE_FIELDS).some((field) => actorCan(user, field.permission))) throw financeReject.forbidden()
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
  return {
    currency: 'SAR',
    accreditedPrice: settings.accreditedPrice,
    bagPrice: settings.bagPrice,
    vatRate: settings.vatRate,
    orderValidityMinutes: settings.orderValidityMinutes,
    orderValidityLimits: { ...ORDER_VALIDITY_LIMITS },
    pricing: {
      accredited: previewOf(settings.accreditedPrice, settings.vatRate),
      bag: previewOf(settings.bagPrice, settings.vatRate),
    },
    editableFields: editableFields(user),
  }
}

function priceError(value) {
  if (typeof value !== 'string' || !PRICE_PATTERN.test(value)) return fieldMessage('price_format')
  return Number(value) > 0 ? null : fieldMessage('price_positive')
}

function vatError(value) {
  if (typeof value !== 'string' || !VAT_PATTERN.test(value) || Number(value) > 100) return fieldMessage('vat_format')
  return null
}

function validityError(value) {
  const { min, max } = ORDER_VALIDITY_LIMITS
  if (!Number.isInteger(value) || value < min || value > max) return fieldMessage('between', { min, max })
  return null
}

function reasonError(value) {
  if (value === undefined || value === null || value === '') return null
  const reason = String(value).trim()
  if (reason.length < 10) return fieldMessage('min', { min: 10 })
  if (reason.length > 1000) return fieldMessage('max', { max: 1000 })
  return null
}

const FIELD_CHECKS = { accreditedPrice: priceError, bagPrice: priceError, vatRate: vatError, orderValidityMinutes: validityError }

function presentFields(body) {
  return Object.keys(FINANCE_FIELDS).filter((field) => Object.hasOwn(body, field))
}

function validated(body) {
  const errors = presentFields(body)
    .map((field) => [field, FIELD_CHECKS[field](body[field])])
    .concat([['reason', reasonError(body.reason)]])
    .filter(([, message]) => message)
  if (errors.length) throw financeReject.validation(Object.fromEntries(errors.map(([field, message]) => [field, [message]])))
}

export function updateFinanceSettings(user, body) {
  const fields = presentFields(body)
  if (fields.some((field) => !actorCan(user, FINANCE_FIELDS[field].permission))) throw financeReject.forbidden()
  validated(body)
  if (!fields.length) return
  if (user.superAdmin && !String(body.reason ?? '').trim()) throw financeReject.reasonRequired()
  const settings = financeState().settings
  fields.forEach((field) => {
    settings[field] = field === 'vatRate' ? normalizedRate(body.vatRate) : body[field]
  })
  persistFinanceState()
}
