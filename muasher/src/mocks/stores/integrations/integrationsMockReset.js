import { resetIntegrationsState } from './integrationsState'
import { resetIntegrationTesting } from './integrationTesting'

export function resetIntegrationsMock() {
  resetIntegrationsState()
  resetIntegrationTesting()
}
