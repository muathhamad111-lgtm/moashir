import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import {
  assessmentReject,
  assessmentRoute,
  readJson,
} from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { authorizeSettings } from '../stores/accredited/accreditedAccess'
import {
  assessmentSettingsResource,
  updateAssessmentSettings,
} from '../stores/accredited/assessmentSettingsUpdate'
import { SETTINGS_PATHS, pathSettingsResource } from '../stores/accredited/pathSettingsStore'
import { updatePathSettings } from '../stores/accredited/pathSettingsUpdate'

const PATH_SETTINGS = '/api/v1/settings/assessment/paths/:path'

function settingsPath(params) {
  if (!SETTINGS_PATHS.includes(params.path)) throw assessmentReject.notFound()
  return params.path
}

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

  http.get(
    PATH_SETTINGS,
    assessmentRoute(({ params }) => {
      authorizeSettings(signedInActor())
      return envelope(pathSettingsResource(settingsPath(params)))
    }),
  ),

  http.put(
    PATH_SETTINGS,
    assessmentRoute(async ({ params, request }) => {
      const user = signedInActor()
      authorizeSettings(user)
      const path = settingsPath(params)
      updatePathSettings(user, path, await readJson(request))
      return envelope(pathSettingsResource(path))
    }),
  ),
]
