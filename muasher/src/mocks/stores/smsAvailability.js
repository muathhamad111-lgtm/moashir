import { smsState } from './integrations/integrationsState'

export const SMS_GATEWAY_STATUS = Object.freeze({
  UNAVAILABLE: 'unavailable',
  TEST: 'test',
  LIVE: 'live',
})

export function smsGatewayAvailable() {
  return Object.values(smsState().rows).some((row) => row?.isActive === true)
}

export function smsGatewayStatus() {
  return smsGatewayAvailable() ? smsState().mode : SMS_GATEWAY_STATUS.UNAVAILABLE
}
