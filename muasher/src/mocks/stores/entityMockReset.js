import { resetEntitiesStore } from './entitiesStore'
import { resetRegionsStore } from './regionsStore'
import { resetEmailChanges } from './entityEmailChanges'
import { resetTermsStore } from './termsStore'
import { resetProfileChanges } from './profileChangesStore'
import { resetAuthSettings } from './authSettingsStore'

export function resetEntitiesMock() {
  resetEntitiesStore()
  resetRegionsStore()
  resetEmailChanges()
  resetTermsStore()
  resetProfileChanges()
  resetAuthSettings()
}
