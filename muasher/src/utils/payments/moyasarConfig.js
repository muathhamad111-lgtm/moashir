import { CHECKOUT_PATTERNS } from '@/constants/payments'

function applePayOf(applePay) {
  if (!applePay) return {}
  return {
    apple_pay: {
      country: applePay.country,
      label: applePay.label,
      validate_merchant_url: applePay.validateMerchantUrl,
    },
  }
}

function intentOf(checkout) {
  if (checkout.pattern !== CHECKOUT_PATTERNS.INTENT || !checkout.paymentIntent) return {}
  return {
    use_payment_intent: true,
    payment_intent: { id: checkout.paymentIntent.id, client_secret: checkout.paymentIntent.clientSecret },
  }
}

export function moyasarConfigOf(checkout, { element, onCompleted }) {
  return {
    element,
    amount: checkout.amountHalalas,
    currency: checkout.currency,
    description: checkout.description,
    publishable_api_key: checkout.publishableKey,
    callback_url: checkout.callbackUrl,
    language: checkout.language,
    methods: [...checkout.methods],
    supported_networks: [...checkout.supportedNetworks],
    metadata: { ...checkout.metadata },
    ...intentOf(checkout),
    ...applePayOf(checkout.applePay),
    on_completed: onCompleted,
  }
}
