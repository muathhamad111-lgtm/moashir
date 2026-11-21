import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import {
  assignmentReasonRules,
  saveAssignmentReasonRules,
} from '../stores/accredited/assignmentReasons'

const ENDPOINT = '/api/v1/settings/assessment/assignment-reasons'
const PERMISSION = 'settings.assessment.manage'

function refusal() {
  if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
  if (!sessionCan(PERMISSION) && !sessionIsSuperAdmin())
    return errorResponse(403, 'forbidden', 'Forbidden.')
  return null
}

function invalidFields(body) {
  return ['assignRequired', 'reassignRequired'].filter((field) => typeof body[field] !== 'boolean')
}

export const assignmentReasonsHandlers = [
  http.get(ENDPOINT, () => refusal() ?? envelope(assignmentReasonRules())),

  http.put(ENDPOINT, async ({ request }) => {
    const refused = refusal()
    if (refused) return refused
    const body = await request.json()
    if (sessionIsSuperAdmin() && !body.reason)
      return errorResponse(422, 'reason_required', 'A reason is required for this action.')
    const invalid = invalidFields(body)
    if (invalid.length)
      return errorResponse(
        422,
        'validation_failed',
        'The given data was invalid.',
        Object.fromEntries(invalid.map((field) => [field, ['The field must be true or false.']])),
      )
    return envelope(saveAssignmentReasonRules(body))
  }),
]
