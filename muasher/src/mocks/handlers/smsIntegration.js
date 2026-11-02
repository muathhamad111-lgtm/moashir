import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import {
  actorIsSuperAdmin,
  actorReference,
  integrationActor,
  integrationRoute,
  readBody,
} from '../stores/integrations/integrationRejection'
import { throttleTest } from '../stores/integrations/integrationTesting'
import {
  msegatTemplates,
  sendTestSms,
  smsResource,
  taqnyatSenders,
  testSmsProvider,
  updateSms,
} from '../stores/integrations/smsIntegration'

const PATH = '/api/v1/settings/sms'

function smsManager() {
  return actorReference(integrationActor('sms'))
}

export const smsIntegrationHandlers = [
  http.get(
    PATH,
    integrationRoute(() => {
      smsManager()
      return envelope(smsResource())
    }),
  ),

  http.put(
    PATH,
    integrationRoute(async ({ request }) => {
      const actor = smsManager()
      const body = await readBody(request)
      return envelope(updateSms(body, actor, { superAdmin: actorIsSuperAdmin() }))
    }),
  ),

  http.post(
    `${PATH}/:provider/test-connection`,
    integrationRoute(({ params }) => {
      const actor = smsManager()
      throttleTest(actor, 'sms:test-connection')
      return envelope(testSmsProvider(params.provider, actor))
    }),
  ),

  http.get(
    `${PATH}/taqnyat/senders`,
    integrationRoute(() => {
      throttleTest(smsManager(), 'sms:senders')
      return envelope(taqnyatSenders())
    }),
  ),

  http.get(
    `${PATH}/msegat/templates`,
    integrationRoute(() => {
      throttleTest(smsManager(), 'sms:templates')
      return envelope(msegatTemplates())
    }),
  ),

  http.post(
    `${PATH}/test`,
    integrationRoute(async ({ request }) => {
      const actor = smsManager()
      throttleTest(actor, 'sms:send')
      return envelope(sendTestSms(await readBody(request)))
    }),
  ),
]
