import { describe, expect, it } from 'vitest'
import { excellenceToolsSettingsGuard } from '@/router/excellenceToolsSettings'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'

describe('excellence-tools settings guard', () => {
  it('sends a hidden page to the settings hub', () => {
    expect(excellenceToolsSettingsGuard(false)()).toEqual({ name: ROUTE_NAMES.ADMIN_SETTINGS })
  })

  it('lets the page open once the switch is on', () => {
    expect(excellenceToolsSettingsGuard(true)()).toBe(true)
  })

  it('lands the maturity texts URL on the settings hub while the switch is off', async () => {
    const { router } = createTestGlobals()
    await router.push('/admin/settings/maturity-texts')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.ADMIN_SETTINGS)
    expect(router.currentRoute.value.path).toBe('/admin/settings')
  })
})
