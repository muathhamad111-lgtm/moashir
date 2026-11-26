import { staffHolding } from '../staffUsers'
import { gatewayRow } from './checkoutObject'
import { financeState, persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { selectableAccounts } from './bankAccountsStore'

export const CHANNELS = ['electronic', 'bank_transfer']
const REVIEW_PERMISSION = 'finance.transfers.review'

function gatewayReady() {
  return gatewayRow() !== null
}

function isReady(channel) {
  return channel === 'electronic' ? gatewayReady() : selectableAccounts().length > 0
}

export function enabledChannels() {
  return CHANNELS.filter((channel) => financeState().channels.includes(channel))
}

export function availableChannels() {
  return enabledChannels().filter(isReady)
}

export function isChannelAvailable(channel) {
  return availableChannels().includes(channel)
}

export function paymentChannelsResource() {
  return {
    channels: enabledChannels(),
    availableChannels: availableChannels(),
    gatewayReady: gatewayReady(),
    activeBankAccountsCount: selectableAccounts().length,
    reviewersCount: staffHolding(REVIEW_PERMISSION).length,
  }
}

export function validatedChannels(body) {
  const channels = body.channels
  const valid =
    Array.isArray(channels) &&
    channels.length >= 1 &&
    channels.length <= CHANNELS.length &&
    new Set(channels).size === channels.length &&
    channels.every((channel) => CHANNELS.includes(channel))
  if (!valid) throw financeReject.validation({ channels: [fieldMessage('choice')] })
  return channels
}

export function updateChannels(channels) {
  if (channels.includes('electronic') && !gatewayReady()) throw financeReject.gatewayNotReady()
  if (channels.includes('bank_transfer') && !selectableAccounts().length)
    throw financeReject.bankAccountRequired()
  financeState().channels = CHANNELS.filter((channel) => channels.includes(channel))
  persistFinanceState()
}
