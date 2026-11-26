const SMS = 'sms'

// The order a part is shown in within its channel: subject and body first, then the e-mail's extra lines.
const ORDER = [
  'emailSubject',
  'subject',
  'title',
  'adminTitle',
  'emailBody',
  'line',
  'body',
  'adminBody',
]

function rank(part) {
  const index = ORDER.indexOf(part)
  return index === -1 ? ORDER.length : index
}

/**
 * The part names of a notification text that belong to one channel (in-app, e-mail or SMS). A core
 * notification has no SMS text, since its channels never change.
 */
export function channelPartsOf(text, channel, isCore = false) {
  if (channel === SMS) return isCore ? [] : [SMS]
  return Object.keys(text.parts)
    .filter((part) => text.parts[part].channel === channel)
    .sort((a, b) => rank(a) - rank(b))
}

/**
 * What the e-mail preview shows: its subject, its body, the button and the template's other lines.
 */
export function emailPreviewParts(text) {
  const parts = channelPartsOf(text, 'email')
  const subject = parts.find((part) => ['emailSubject', 'subject'].includes(part))
  const body = parts.find((part) => ['emailBody', 'line'].includes(part))
  const extras = parts.filter((part) => ![subject, body, 'action'].includes(part))
  return { subject, body, action: parts.includes('action') ? 'action' : null, extras }
}
