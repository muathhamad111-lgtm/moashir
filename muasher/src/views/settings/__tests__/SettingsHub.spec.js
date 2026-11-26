import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import SettingsHub from '@/views/settings/Index.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

const excellenceTools = vi.hoisted(() => ({ enabled: false }))
vi.mock('@/constants/insights', async (importOriginal) => ({
  ...(await importOriginal()),
  get EXCELLENCE_TOOLS_SETTINGS_ENABLED() {
    return excellenceTools.enabled
  },
}))

async function mountHub(roles = ['system_admin']) {
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  const wrapper = mount(SettingsHub, { global: globals })
  await flushPromises()
  return wrapper
}

function cardLinks(wrapper) {
  return wrapper.findAll('.settings-hub__card').map((card) => card.attributes('href'))
}

beforeEach(() => {
  excellenceTools.enabled = false
})

describe('settings hub — excellence-tools settings', () => {
  it('hides the maturity texts card while the service awaits approval', async () => {
    const links = cardLinks(await mountHub())
    expect(links).not.toContain('/admin/settings/maturity-texts')
    expect(links).toContain('/admin/settings/homepage')
    expect(links).toContain('/admin/settings/assessment')
  })

  it('shows the maturity texts card again once the switch is on', async () => {
    excellenceTools.enabled = true
    expect(cardLinks(await mountHub())).toContain('/admin/settings/maturity-texts')
  })
})
