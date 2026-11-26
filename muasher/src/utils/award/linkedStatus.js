import { fromCanonicalStatus } from '@/utils/canonicalStatus'

// A status filter carried by a link (a dashboard card or a notification): either spelling is accepted, and a value
// the list does not offer is ignored rather than sent.
export function linkedStatus(value, allowed) {
  const status = fromCanonicalStatus(typeof value === 'string' ? value : '')
  return allowed.includes(status) ? status : ''
}
