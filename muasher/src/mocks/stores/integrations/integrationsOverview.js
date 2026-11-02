import { paymentOverview } from './paymentIntegration'
import { smsOverview } from './smsIntegration'
import { emailOverview } from './emailIntegration'
import { aiOverview } from './aiIntegration'

const OVERVIEW_ROWS = { payment: paymentOverview, sms: smsOverview, email: emailOverview, ai: aiOverview }

export function overviewRows(kinds) {
  return kinds.map((kind) => ({ kind, ...OVERVIEW_ROWS[kind]() }))
}
