import { persistFinanceState } from './financeState'
import { financeReject } from './financeRejection'
import { recordEvent, transition } from './paymentLifecycle'

export function cancelCardOrder(payment) {
  if (payment.channel === 'bank_transfer') throw financeReject.channelNotSupported()
  if (payment.status !== 'created' || payment.providerPaymentId)
    throw financeReject.illegalTransition(payment.status, 'cancel')
  transition(payment, 'cancelled', 'cancel')
  payment.cancellationCause = 'cancelled_by_entity'
  recordEvent(payment, { source: 'checkout', eventType: 'order_cancelled', result: 'applied' })
  persistFinanceState()
  return payment
}
