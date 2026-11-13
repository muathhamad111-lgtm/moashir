import { pathPricingOf } from '../finance/priceVersions'
import { assertAvailable, pathReject } from '../finance/pathRejection'
import { openOrderFor, paidWithoutRequest } from '../periodic/paidRequests'
import { assessmentReject } from './assessmentRejection'
import { openSelfDraftsOf } from './assessmentsStore'

const SELF = 'self'

export function assertSelfPurchasable(entity) {
  const draft = openSelfDraftsOf(entity.id)[0]
  if (draft) throw assessmentReject.draftExists(draft.id)
  if (paidWithoutRequest(entity.id, SELF))
    throw pathReject.beingProcessed('self.payment_being_processed')
}

export function assertSelfStartable(entity) {
  const pricing = pathPricingOf(SELF)
  assertAvailable(SELF, pricing)
  const draft = openSelfDraftsOf(entity.id)[0]
  if (draft) throw assessmentReject.draftExists(draft.id)
  const order = openOrderFor(entity.id, SELF)
  if (order) throw pathReject.openOrderExists(order)
  assertSelfPurchasable(entity)
  if (!pricing.isFree) throw pathReject.paymentRequired({ path: SELF, purpose: SELF, pricing })
}
