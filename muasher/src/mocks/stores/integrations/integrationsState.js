import { aiSeed, emailSeed, paymentSeed, smsSeed } from '../../fixtures/integrations'

let state = null

export function resetIntegrationsState() {
  state = { payment: paymentSeed(), sms: smsSeed(), email: emailSeed(), ai: aiSeed() }
}

resetIntegrationsState()

export function paymentState() {
  return state.payment
}

export function smsState() {
  return state.sms
}

export function emailState() {
  return state.email
}

export function aiState() {
  return state.ai
}
