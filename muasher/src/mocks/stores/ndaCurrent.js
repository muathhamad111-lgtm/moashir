import { legalVersion, versionsOf } from './legalVersionsStore'
import { ndaStatusOf } from './ndaStore'

export function currentNda() {
  const current = versionsOf('nda').find((item) => item.status === 'current')
  return current ? legalVersion(current.id) : null
}

export function ndaRequiredFor(user) {
  return ndaStatusOf(user, currentNda()) === 'pending'
}
