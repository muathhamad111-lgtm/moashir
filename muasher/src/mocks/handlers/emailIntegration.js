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
import { emailResource, sendTestEmail, testEmail, updateEmail } from '../stores/integrations/emailIntegration'

const PATH = '/api/v1/settings/email'

function mailManager() {
  return actorReference(integrationActor('email'))
}

export const emailIntegrationHandlers = [
  http.get(
    PATH,
    integrationRoute(() => {
      mailManager()
      return envelope(emailResource())
    }),
  ),

  http.put(
    PATH,
    integrationRoute(async ({ request }) => {
      const actor = mailManager()
      const body = await readBody(request)
      return envelope(updateEmail(body, actor, { superAdmin: actorIsSuperAdmin() }))
    }),
  ),

  http.post(
    `${PATH}/test-connection`,
    integrationRoute(() => {
      const actor = mailManager()
      throttleTest(actor, 'email:connection')
      return envelope(testEmail(actor))
    }),
  ),

  http.post(
    `${PATH}/test`,
    integrationRoute(async ({ request }) => {
      const actor = mailManager()
      throttleTest(actor, 'email:send')
      return envelope(sendTestEmail(await readBody(request)))
    }),
  ),
]
