import { identityService } from '@/services/identityService'
import { regionsService } from '@/services/regionsService'
import { termsService } from '@/services/termsService'
import { prefetchRead, takeRead } from '@/utils/prefetchedReads'

export const REGISTRATION_READS = Object.freeze({
  terms: { key: 'registration.terms', fetch: () => termsService.current() },
  regions: { key: 'registration.regions', fetch: () => regionsService.listRegions() },
  options: { key: 'registration.options', fetch: () => identityService.registrationOptions() },
})

export function prefetchRegistrationReads() {
  for (const read of Object.values(REGISTRATION_READS)) prefetchRead(read.key, read.fetch)
}

export function takeRegistrationRead(name) {
  const read = REGISTRATION_READS[name]
  return takeRead(read.key, read.fetch)
}
