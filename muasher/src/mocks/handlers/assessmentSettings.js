import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { assessmentRoute, readJson } from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { authorizeSettings } from '../stores/accredited/accreditedAccess'
import {
  assessmentSettingsResource,
  updateAssessmentSettings,
} from '../stores/accredited/assessmentSettingsUpdate'

export const assessmentSettingsHandlers = [
  http.get(
    '/api/v1/settings/assessment',
    assessmentRoute(() => {
      authorizeSettings(signedInActor())
      return envelope(assessmentSettingsResource())
    }),
  ),

  http.put(
    '/api/v1/settings/assessment',
    assessmentRoute(async ({ request }) => {
      const user = signedInActor()
      authorizeSettings(user)
      updateAssessmentSettings(user, await readJson(request))
      return envelope(assessmentSettingsResource())
    }),
  ),
]
