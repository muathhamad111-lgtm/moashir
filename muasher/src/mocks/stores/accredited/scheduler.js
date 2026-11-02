import { sweepPaidAccreditedPayments } from './accreditedOpening'
import { settleExpiredCompletions } from './completionExpiry'
import { runAwardSchedule } from '../award/cycleTransitions'

export function runAccreditedSchedule() {
  sweepPaidAccreditedPayments()
  runAwardSchedule()
  settleExpiredCompletions()
}
