import { paymentState } from '../integrations/integrationsState'
import { toMinor } from './financeMoney'

export const APPLE_PAY_VALIDATION_URL = 'https://api.moyasar.com/v1/applepay/initiate'
const APPLE_PAY_COUNTRY = 'SA'
const MOCK_APP_ENV = 'local'

export function gatewayRow() {
  const gateway = paymentState()
  const row = gateway.rows[gateway.mode]
  return row?.publishableKey && row.secretKey?.set && row.isActive ? { mode: gateway.mode, row } : null
}

export function linkedPaths() {
  return [...paymentState().linkedPaths]
}

export function clientSecretOf(intentId) {
  return `${intentId}_secret_mock`
}

export function callbackUrlOf(paymentId) {
  return `${window.location.origin}/payments/${paymentId}/return`
}

export function metadataOf(payment) {
  return {
    moashir_payment_id: payment.id,
    moashir_reference: payment.reference,
    purpose: payment.purpose,
    app_env: MOCK_APP_ENV,
  }
}

function applePayOf(gateway, locale) {
  if (!gateway.methods.includes('applepay')) return null
  return {
    country: APPLE_PAY_COUNTRY,
    label: gateway.applePayLabel?.[locale] ?? gateway.applePayLabel?.ar ?? '',
    validateMerchantUrl: APPLE_PAY_VALIDATION_URL,
  }
}

function presentable(payment) {
  return payment.status === 'created' && payment.providerIntentId && new Date(payment.expiresAt).getTime() > Date.now()
}

export function checkoutOf(payment, locale) {
  const configured = gatewayRow()
  if (!presentable(payment) || !configured) return null
  const gateway = paymentState()
  const row = gateway.rows[payment.mode] ?? configured.row
  return {
    pattern: 'intent',
    mode: payment.mode,
    publishableKey: row.publishableKey,
    amountHalalas: toMinor(payment.total),
    currency: payment.currency,
    description: `Moashir ${payment.reference}`,
    callbackUrl: callbackUrlOf(payment.id),
    language: locale === 'en' ? 'en' : 'ar',
    methods: [...gateway.methods],
    supportedNetworks: [...gateway.supportedNetworks],
    metadata: metadataOf(payment),
    paymentIntent: { id: payment.providerIntentId, clientSecret: clientSecretOf(payment.providerIntentId) },
    applePay: applePayOf(gateway, locale),
    expiresAt: payment.expiresAt,
  }
}
