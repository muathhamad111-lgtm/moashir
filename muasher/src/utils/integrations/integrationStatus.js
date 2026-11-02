import { INTEGRATION_STATUSES as S } from '@/constants/integrations'

export const INTEGRATION_STATUS_VARIANTS = Object.freeze({
  [S.NOT_CONFIGURED]: 'muted',
  [S.CONFIGURED_UNTESTED]: 'warning',
  [S.TEST_PASSED]: 'info',
  [S.TEST_FAILED]: 'danger',
  [S.ACTIVE]: 'success',
})

export const INTEGRATION_STATUS_ICONS = Object.freeze({
  [S.NOT_CONFIGURED]: 'fa-solid fa-circle-minus',
  [S.CONFIGURED_UNTESTED]: 'fa-solid fa-circle-question',
  [S.TEST_PASSED]: 'fa-solid fa-circle-check',
  [S.TEST_FAILED]: 'fa-solid fa-circle-xmark',
  [S.ACTIVE]: 'fa-solid fa-power-off',
})

export function testStatusOf(lastTest) {
  if (!lastTest) return S.CONFIGURED_UNTESTED
  return lastTest.ok ? S.TEST_PASSED : S.TEST_FAILED
}

export function integrationStatusOf({ configured, active, lastTest }) {
  if (!configured) return S.NOT_CONFIGURED
  if (active) return S.ACTIVE
  return testStatusOf(lastTest)
}
