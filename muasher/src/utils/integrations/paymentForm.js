import { INTEGRATION_MODES } from '@/constants/integrations'
import { keptSecret, withSecrets } from './secretValue'
import { changedValues, trimmedOrNull, writesEverything } from './partialPayload'

const MODES = Object.values(INTEGRATION_MODES)

function keyPairValues(pair) {
  return {
    publishableKey: pair?.publishableKey ?? '',
    secretKey: keptSecret(),
    webhookSecret: keptSecret(),
  }
}

export function paymentFormValues(resource) {
  return {
    mode: resource.mode,
    active: Boolean(resource.active),
    test: keyPairValues(resource.test),
    live: keyPairValues(resource.live),
    methods: [...(resource.methods ?? [])],
    supportedNetworks: [...(resource.supportedNetworks ?? [])],
    applePayLabel: { ar: resource.applePayLabel?.ar ?? '', en: resource.applePayLabel?.en ?? '' },
    confirmations: {
      applePayDomain: resource.confirmations?.applePayDomain?.confirmed === true,
      stcpayActivation: resource.confirmations?.stcpayActivation?.confirmed === true,
    },
    linkedPaths: [...(resource.linkedPaths ?? [])],
  }
}

function settingsOf(values) {
  return {
    mode: values.mode,
    active: values.active,
    methods: values.methods,
    supportedNetworks: values.supportedNetworks,
    applePayLabel: { ar: values.applePayLabel.ar.trim(), en: values.applePayLabel.en.trim() },
    linkedPaths: values.linkedPaths,
  }
}

function keyPairPayload(pair, initial, full) {
  const publicFields = changedValues(
    { publishableKey: trimmedOrNull(pair.publishableKey) },
    { publishableKey: trimmedOrNull(initial.publishableKey) },
    { full },
  )
  return withSecrets(publicFields, { secretKey: pair.secretKey, webhookSecret: pair.webhookSecret })
}

export function paymentPayload(values, resource) {
  const full = writesEverything(resource)
  const initial = paymentFormValues(resource)
  const payload = changedValues(settingsOf(values), settingsOf(initial), { full })
  const confirmations = changedValues(values.confirmations, initial.confirmations, { full })
  if (Object.keys(confirmations).length) payload.confirmations = confirmations
  for (const mode of MODES) {
    const pair = keyPairPayload(values[mode], initial[mode], full)
    if (Object.keys(pair).length) payload[mode] = pair
  }
  return payload
}

export function paymentSecretsChanged(payload) {
  return MODES.some(
    (mode) => payload[mode] && ('secretKey' in payload[mode] || 'webhookSecret' in payload[mode]),
  )
}

export function isLiveReady(resource) {
  return resource?.live?.configured === true && resource.live.lastTest?.ok === true
}
