import { currentNda } from './ndaCurrent'
import { inNdaScope, ndaText } from './ndaStore'

const MESSAGES = {
  ar: 'وافق على النسخة السارية من اتفاقية عدم الإفصاح.',
  en: 'Accept the current version of the non-disclosure agreement.',
}

function requiredFor(user) {
  const current = currentNda()
  return current && inNdaScope({ ...user, roles: user.roles ?? [] }) ? current : null
}

export function invitationNda(user) {
  const current = requiredFor(user)
  return current ? ndaText(current) : null
}

export function invitationNdaErrors(request, user, body) {
  const current = requiredFor(user)
  if (!current || Number(body.ndaVersionId) === current.id) return {}
  const locale = request.headers.get('Accept-Language')?.startsWith('en') ? 'en' : 'ar'
  return { ndaVersionId: [MESSAGES[locale]] }
}
