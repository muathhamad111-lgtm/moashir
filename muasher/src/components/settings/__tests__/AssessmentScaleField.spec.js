import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AssessmentGeneralSettings from '@/components/settings/AssessmentGeneralSettings.vue'
import { assessmentSettingsService } from '@/services/assessmentSettingsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/assessmentSettingsService', () => ({
  assessmentSettingsService: { get: vi.fn(), update: vi.fn() },
}))

function settingsWith(overrides = {}) {
  return {
    generalUploadMaxFileMb: 20,
    advancedMinEntityAgeYears: 3,
    advancedDisqualificationScore: '60.00',
    committeeEntityDataVisibility: 'name_only',
    scaleKind: 'relative',
    publishedScaleKind: 'of-10',
    ...overrides,
  }
}

async function mountSettings(settings, locale = 'en') {
  assessmentSettingsService.get.mockResolvedValue({ data: settings })
  const wrapper = mount(AssessmentGeneralSettings, {
    global: { ...createTestGlobals({ locale }), stubs: { teleport: true } },
  })
  await flushPromises()
  return wrapper
}

describe('assessment scale setting', () => {
  it('shows the default type for new main criteria and says each criterion keeps its own type', async () => {
    const wrapper = await mountSettings(settingsWith())
    const field = wrapper.find('[name="scaleKind"]')

    expect(field.attributes('data-value')).toBe('relative')
    expect(wrapper.text()).toContain('Each new main criterion starts with this type')
  })

  it('explains the scale without a published version, in Arabic', async () => {
    const wrapper = await mountSettings(settingsWith({ publishedScaleKind: null }), 'ar')

    expect(wrapper.text()).toContain('نوع التقييم الافتراضي')
    expect(wrapper.text()).toContain('يبدأ كل معيار رئيسي جديد بهذا النوع')
  })
})
