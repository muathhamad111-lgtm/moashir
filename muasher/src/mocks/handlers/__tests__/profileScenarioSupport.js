import { afterEach } from 'vitest'
import { setMockScenario } from '@/mocks/stores/committee/committeeScenario'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { PROFILE_INCOMPLETE_SCENARIO } from '@/mocks/stores/entityProfileScenario'

export function startWithIncompleteProfile() {
  setMockScenario(PROFILE_INCOMPLETE_SCENARIO)
  resetEntitiesMock()
}

export function resetProfileScenarioAfterEach() {
  afterEach(() => setMockScenario(null))
}
