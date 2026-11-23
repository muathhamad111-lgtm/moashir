import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AssessmentGeneralSettings from '@/components/settings/AssessmentGeneralSettings.vue'
import { assessmentSettingsService } from '@/services/assessmentSettingsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/assessmentSettingsService', () => ({
  assessmentSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const SETTINGS = {
  generalUploadMaxFileMb: 20,
  advancedMinEntityAgeYears: 3,
  advancedDisqualificationScore: '60.00',
  committeeEntityDataVisibility: 'name_only',
  advancedCycleConcurrency: 'concurrent',
  runningCycles: { running: 2, openForSubmission: 1 },
  scaleKind: 'of-10',
  publishedScaleKind: null,
}

function reasonRequired() {
  const error = new Error('reason_required')
  error.normalized = { status: 422, code: 'reason_required', message: 'Reason', fieldErrors: {} }
  return error
}

async function mountSettings(locale = 'en', overrides = { advancedCycleConcurrency: 'single' }) {
  assessmentSettingsService.get.mockResolvedValue({ data: { ...SETTINGS, ...overrides } })
  const wrapper = mount(AssessmentGeneralSettings, {
    attachTo: document.body,
    global: { ...createTestGlobals({ locale }), stubs: { teleport: true } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('award cycle concurrency setting', () => {
  it('offers the three modes with the description of the chosen one', async () => {
    const wrapper = await mountSettings()
    const options = wrapper.findAll('input[name="advancedCycleConcurrency"]')

    expect(options.map((option) => option.attributes('value'))).toEqual([
      'single',
      'staggered',
      'concurrent',
    ])
    expect(options[0].element.checked).toBe(true)
    expect(wrapper.find('[data-testid="cycle-concurrency-hint"]').text()).toBe(
      'A new cycle opens only after the running cycle is closed.',
    )

    await options[2].setValue(true)
    expect(wrapper.find('[data-testid="cycle-concurrency-hint"]').text()).toContain(
      'each entity chooses the cycle it applies to',
    )
  })

  it('says the chosen mode applies to new cycles only when more cycles run than it allows', async () => {
    const wrapper = await mountSettings('en', {})
    const options = wrapper.findAll('input[name="advancedCycleConcurrency"]')
    const notice = () => wrapper.find('[data-testid="cycle-concurrency-future-only"]')

    expect(notice().exists()).toBe(false)
    await options[1].setValue(true)
    expect(notice().exists()).toBe(false)
    await options[0].setValue(true)
    expect(notice().text()).toBe('Applies to new cycles only')
    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeUndefined()
  })

  it('shows the new-cycles-only notice in Arabic', async () => {
    const wrapper = await mountSettings('ar', {
      runningCycles: { running: 2, openForSubmission: 2 },
      advancedCycleConcurrency: 'staggered',
    })

    expect(wrapper.find('[data-testid="cycle-concurrency-future-only"]').text()).toBe(
      'تسري على الدورات الجديدة فقط',
    )
  })

  it('labels the modes in Arabic', async () => {
    const wrapper = await mountSettings('ar')

    expect(wrapper.text()).toContain('تزامن دورات الجائزة')
    expect(wrapper.text()).toContain('دورة واحدة قائمة فقط')
    expect(wrapper.text()).toContain('دورة جديدة مع وجود دورة غير مغلقة')
    expect(wrapper.text()).toContain('عدة دورات في الفترة نفسها')
  })

  it('sends only the changed mode and asks for the reason the server requires', async () => {
    const wrapper = await mountSettings()
    assessmentSettingsService.update.mockRejectedValueOnce(reasonRequired())

    await wrapper.findAll('input[name="advancedCycleConcurrency"]')[1].setValue(true)
    await wrapper.find('form.settings-form').trigger('submit')

    await vi.waitFor(() =>
      expect(assessmentSettingsService.update).toHaveBeenCalledWith({
        advancedCycleConcurrency: 'staggered',
      }),
    )
    await vi.waitFor(() => expect(document.body.textContent).toContain('Reason for the change'))
    wrapper.unmount()
  })
})
