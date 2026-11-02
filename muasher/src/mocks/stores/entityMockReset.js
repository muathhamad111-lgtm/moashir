import { resetEntitiesStore } from './entitiesStore'
import { resetRegionsStore } from './regionsStore'
import { resetEmailChanges } from './entityEmailChanges'
import { resetPrivacyRequests } from './privacyRequestsStore'
import { resetTermsStore } from './termsStore'
import { resetProfileChanges } from './profileChangesStore'

export function resetEntitiesMock() {
  resetEntitiesStore()
  resetRegionsStore()
  resetEmailChanges()
  resetPrivacyRequests()
  resetTermsStore()
  resetProfileChanges()
}
