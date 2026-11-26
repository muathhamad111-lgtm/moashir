import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
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

const PANELS = {
  AssessmentGeneralSettings: true,
  PathSettingsPanel: { props: ['path'], template: '<div class="stub-path" :data-path="path" />' },
  InsightsSettingsCard: { template: '<div data-testid="insights-settings" />' },
  CertificateSettingsCard: true,
  PathPriceCard: true,
}

// The tab list is read once per module load, so each case loads the page afresh.
async function mountAt(query) {
  vi.resetModules()
  const { default: AssessmentSettings } = await import('@/views/settings/Assessment.vue')
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  await globals.router.push({ path: '/admin/settings/assessment', query })
  const wrapper = mount(AssessmentSettings, { global: { ...globals, stubs: PANELS } })
  await flushPromises()
  return wrapper
}

function tabLabels(wrapper) {
  return wrapper.findAll('[role="tab"]').map((tab) => tab.text())
}

beforeEach(() => {
  excellenceTools.enabled = false
})

describe('assessment settings — excellence-tools tab', () => {
  it('leaves the excellence-tools tab out while the service awaits approval', async () => {
    const wrapper = await mountAt({})
    expect(tabLabels(wrapper)).not.toContain('Excellence tools')
    expect(tabLabels(wrapper)).toContain('General settings')
  })

  it('opens the first tab from a link to the hidden tab', async () => {
    const wrapper = await mountAt({ tab: 'insights' })
    expect(wrapper.find('[data-testid="insights-settings"]').exists()).toBe(false)
    expect(wrapper.find('[role="tab"][aria-selected="true"]').text()).toBe(tabLabels(wrapper)[0])
  })

  it('brings the tab back once the switch is on', async () => {
    excellenceTools.enabled = true
    const wrapper = await mountAt({ tab: 'insights' })
    expect(tabLabels(wrapper)).toContain('Excellence tools')
    expect(wrapper.find('[data-testid="insights-settings"]').exists()).toBe(true)
  })
})
