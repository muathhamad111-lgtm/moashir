import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import {
  assessmentReject,
  assessmentRoute,
  readJson,
} from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { sessionCan } from '../stores/sessionPermissions'
import {
  certificateSettingsResource,
  updateCertificateSettings,
} from '../stores/certificateSettingsStore'

const SETTINGS = '/api/v1/settings/certificates'
const MANAGE = 'settings.certificates.manage'

function manager() {
  const user = signedInActor()
  if (!user.superAdmin && !sessionCan(MANAGE)) throw assessmentReject.forbidden()
  return user
}

export const certificateSettingsHandlers = [
  http.get(
    SETTINGS,
    assessmentRoute(() => {
      manager()
      return envelope(certificateSettingsResource())
    }),
  ),

  http.put(
    SETTINGS,
    assessmentRoute(async ({ request }) => {
      const user = manager()
      updateCertificateSettings(user, await readJson(request))
      return envelope(certificateSettingsResource())
    }),
  ),
]
