import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { signedInActor } from '../stores/finance/financeAccess'
import { financeRoute, readJson } from '../stores/finance/financeRejection'
import {
  authorizeFinanceSettings,
  financeSettingsResource,
  updateFinanceSettings,
} from '../stores/finance/financeSettings'

export const financeSettingsHandlers = [
  http.get(
    '/api/v1/settings/finance',
    financeRoute(() => {
      const user = signedInActor()
      authorizeFinanceSettings(user)
      return envelope(financeSettingsResource(user))
    }),
  ),

  http.put(
    '/api/v1/settings/finance',
    financeRoute(async ({ request }) => {
      const user = signedInActor()
      authorizeFinanceSettings(user)
      updateFinanceSettings(user, await readJson(request))
      return envelope(financeSettingsResource(user))
    }),
  ),
]
