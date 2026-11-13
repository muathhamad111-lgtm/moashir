import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import {
  INTEGRATION_PERMISSIONS,
  integrationReject,
  integrationRoute,
} from '../stores/integrations/integrationRejection'
import { overviewRows } from '../stores/integrations/integrationsOverview'
import { paymentTestModeInProduction } from '../stores/integrations/paymentIntegration'

const KIND_ORDER = ['payment', 'sms', 'email', 'ai']

function managedKinds() {
  if (!currentSessionUser()) throw integrationReject.unauthenticated()
  const kinds = KIND_ORDER.filter((kind) => sessionCan(INTEGRATION_PERMISSIONS[kind]))
  if (!kinds.length) throw integrationReject.forbidden()
  return kinds
}

function overviewMeta(kinds) {
  return { paymentTestModeInProduction: kinds.includes('payment') && paymentTestModeInProduction() }
}

export const integrationsHandlers = [
  http.get(
    '/api/v1/settings/integrations',
    integrationRoute(() => {
      const kinds = managedKinds()
      return envelope(overviewRows(kinds), overviewMeta(kinds))
    }),
  ),
]
