import { http } from '../contract/http'
import { errorResponse } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { failsRandomly } from '../contract/faults'
import { authNotices } from '../fixtures/authMessages'
import { readJson } from '../stores/entityRejection'
import { accountOfIdentifier, parseIdentifier } from '../stores/signInIdentifiers'
import { codeChannelFor } from '../stores/signInCodeChannel'
import { codeRequestWaitSeconds, recordCodeRequest } from '../stores/signInCodeRequests'
import { signInCodeChallenge } from '../stores/signInCodeIssuer'
import { otpRequired } from '../stores/signInFlow'
import { authField, authReject, authRoute } from './authRoute'

const LATENCY_MS = 380

const maybeFail = failsRandomly(0.05)

function identifierOf(body) {
  const typed = String(body.identifier ?? '').trim()
  if (!typed) throw authReject.validation({ identifier: [authField('field.required')] })
  const identifier = parseIdentifier(typed)
  if (!identifier) throw authReject.validation({ identifier: [authField('field.identifier')] })
  return identifier
}

function assertRequestAllowed(identifier) {
  const wait = codeRequestWaitSeconds(identifier)
  if (wait) throw authReject.rateLimited(wait)
  recordCodeRequest(identifier)
}

export const signInCodeHandlers = [
  http.post(
    '/api/v1/auth/otp/request',
    authRoute(async ({ request }) => {
      if (maybeFail()) return errorResponse(500, 'server_error', 'The code could not be sent')
      const identifier = identifierOf(await readJson(request))
      assertRequestAllowed(identifier)
      const channel = codeChannelFor(identifier)
      const challenge = signInCodeChallenge(accountOfIdentifier(identifier), channel)
      return otpRequired(challenge, { message: authNotices.signInCodeSent[requestLocale(request)] })
    }, LATENCY_MS),
  ),
]
