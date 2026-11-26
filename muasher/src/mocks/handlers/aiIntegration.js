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
  aiResource,
  listModels,
  testAi,
  testAnswer,
  updateAi,
} from '../stores/integrations/aiIntegration'

const PATH = '/api/v1/settings/ai'

function aiManager() {
  return actorReference(integrationActor('ai'))
}

export const aiIntegrationHandlers = [
  http.get(
    PATH,
    integrationRoute(() => {
      aiManager()
      return envelope(aiResource())
    }),
  ),

  http.put(
    PATH,
    integrationRoute(async ({ request }) => {
      const actor = aiManager()
      const body = await readBody(request)
      return envelope(updateAi(body, actor, { superAdmin: actorIsSuperAdmin() }))
    }),
  ),

  http.get(
    `${PATH}/models`,
    integrationRoute(({ request }) => {
      throttleTest(aiManager(), 'ai:models')
      const { provider, models } = listModels(new URL(request.url).searchParams.get('provider'))
      return envelope(models, { provider })
    }),
  ),

  http.post(
    `${PATH}/test-connection`,
    integrationRoute(async ({ request }) => {
      const actor = aiManager()
      const { provider } = await readBody(request)
      throttleTest(actor, 'ai:test-connection')
      return envelope(testAi(provider, actor))
    }),
  ),

  http.post(
    `${PATH}/test-answer`,
    integrationRoute(async ({ request }) => {
      const actor = aiManager()
      const { provider } = await readBody(request)
      throttleTest(actor, 'ai:test-answer')
      return envelope(testAnswer(provider))
    }),
  ),
]
