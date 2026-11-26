import { INTEGRATION_STATUSES as S } from '@/constants/integrations'

export const INTEGRATION_STATUS_VARIANTS = Object.freeze({
  [S.NOT_CONFIGURED]: 'muted',
  [S.CONFIGURED_UNTESTED]: 'warning',
  [S.TEST_PASSED]: 'info',
  [S.TEST_FAILED]: 'danger',
  [S.ACTIVE]: 'success',
})

export const INTEGRATION_STATUS_ICONS = Object.freeze({
  [S.NOT_CONFIGURED]: 'minus',
  [S.CONFIGURED_UNTESTED]: 'question-circle',
  [S.TEST_PASSED]: 'check-circle',
  [S.TEST_FAILED]: 'x',
  [S.ACTIVE]: 'power',
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
