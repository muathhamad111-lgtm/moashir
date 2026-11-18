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
  it('shows the chosen scale and explains when it takes effect against the published scale', async () => {
    const wrapper = await mountSettings(settingsWith())
    const field = wrapper.find('[name="scaleKind"]')

    expect(field.attributes('data-value')).toBe('relative')
    expect(wrapper.text()).toContain('The published version uses the “Out of 10” scale.')
  })

  it('explains the scale without a published version, in Arabic', async () => {
    const wrapper = await mountSettings(settingsWith({ publishedScaleKind: null }), 'ar')

    expect(wrapper.text()).toContain('مقياس التقييم')
    expect(wrapper.text()).toContain('يُطبَّق المقياس على إصدار المعايير الذي يُنشر بعد الحفظ')
  })
})
