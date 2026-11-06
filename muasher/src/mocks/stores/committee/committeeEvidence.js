import { isInCommittee } from '../award/advancedStates'
import { findSeat, liveRowsOfRecord } from './committeeState'

export function readsCommitteeEvidence(user, record) {
  return (
    isInCommittee(record.status) &&
    liveRowsOfRecord(record.id).some((row) => findSeat(row.seatId).userId === user.id)
  )
}
