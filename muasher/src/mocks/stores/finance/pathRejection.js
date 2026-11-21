import { MockRejection } from '../../contract/rejection'

export const pathReject = {
  unavailable: (path, availability) =>
    new MockRejection(409, 'path.unavailable', { meta: { path, availability } }),
  paymentRequired: (meta) => new MockRejection(409, 'path.payment_required', { meta }),
  notRequired: (purpose) => new MockRejection(409, 'payment.not_required', { meta: { purpose } }),
  openOrderExists: (payment) =>
    new MockRejection(409, 'payment.open_order_exists', {
      meta: { paymentId: payment.id, channel: payment.channel, status: payment.status },
    }),
  beingProcessed: (code) => new MockRejection(409, code),
  cycleNotFound: () => new MockRejection(404, 'payment.award_cycle_not_found'),
}

export function assertAvailable(path, pricing) {
  if (pricing.availability !== 'available') throw pathReject.unavailable(path, pricing.availability)
}
