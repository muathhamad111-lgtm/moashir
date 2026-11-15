import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ProfileSectionForm from '@/components/entities/profile/ProfileSectionForm.vue'
import { entitiesService } from '@/services/entitiesService'
import { useEntityStore } from '@/stores/entity'
import { createTestGlobals } from '@/test-utils'
import { toOwnEntity } from '@/services/mappers/ownEntity'
import { sectionViewsOf } from '@/utils/entityProfile/sections'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { getMine: vi.fn(), saveProfileSection: vi.fn() },
}))

function profileWith(overrides = {}) {
  return toOwnEntity({
    name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    governanceGrade: null,
    employeesCount: null,
    annualBeneficiariesCount: null,
    hasStrategicPlan: true,
    canEdit: true,
    profileSections: [
      {
        key: 'governance',
        revision: 'r-1',
        fields: [
          { key: 'governance_grade', requirement: 'completion' },
          { key: 'employees_count', requirement: 'completion' },
          { key: 'annual_beneficiaries_count', requirement: 'completion' },
          { key: 'has_strategic_plan', requirement: 'completion' },
        ],
      },
    ],
    profileCompletion: {
      percent: 60,
      counted: 15,
      completedCount: 9,
      sections: [
        {
          key: 'governance',
          state: 'incomplete',
          missingCount: 3,
          missingFields: ['governance_grade', 'employees_count', 'annual_beneficiaries_count'],
        },
      ],
    },
    ...overrides,
  })
}

function failure(status, code, fieldErrors = {}) {
  return Object.assign(new Error(code), {
    response: { status },
    normalized: { status, code, message: code, fieldErrors },
  })
}

async function factory({ focusField = null } = {}) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  const store = useEntityStore()
  store.profile = profileWith()
  const wrapper = mount(ProfileSectionForm, {
    global: globals,
    attachTo: document.body,
    props: {
      section: sectionViewsOf(store.profile)[0],
      profile: store.profile,
      focusField,
      cityOptionsFor: () => [],
    },
  })
  await flushPromises()
  return { wrapper, store }
}

async function submit(wrapper) {
  await wrapper.find('form').trigger('submit')
  for (let round = 0; round < 4; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

beforeEach(() => vi.clearAllMocks())

describe('ProfileSectionForm', () => {
  it('focuses the requested field and marks no optional input with «*»', async () => {
    const { wrapper } = await factory({ focusField: 'employeesCount' })
    await flushPromises()
    expect(document.activeElement?.getAttribute('name')).toBe('employeesCount')
    expect(wrapper.findAll('.field--required')).toHaveLength(0)
    wrapper.unmount()
  })

  it('saves the full section with the base revision and emits saved', async () => {
    entitiesService.saveProfileSection.mockResolvedValue({
      data: profileWith({ governanceGrade: '82.50' }),
      meta: { section: 'governance', changedFields: ['governance_grade'] },
    })
    const { wrapper } = await factory()
    await wrapper.find('[name="governanceGrade"]').setValue('82.5')
    expect(wrapper.emitted('dirty').at(-1)).toEqual([true])
    await submit(wrapper)
    expect(entitiesService.saveProfileSection).toHaveBeenCalledWith('governance', {
      baseRevision: 'r-1',
      governanceGrade: '82.50',
      employeesCount: null,
      annualBeneficiariesCount: null,
      hasStrategicPlan: true,
    })
    expect(wrapper.emitted('saved')).toHaveLength(1)
    wrapper.unmount()
  })

  it('reloads the section with a short message after a stale save (409)', async () => {
    entitiesService.saveProfileSection.mockRejectedValue(failure(409, 'profile.section_stale'))
    entitiesService.getMine.mockResolvedValue({ data: profileWith({ employeesCount: 21 }) })
    const { wrapper } = await factory()
    await wrapper.find('[name="employeesCount"]').setValue('14')
    await submit(wrapper)
    expect(entitiesService.getMine).toHaveBeenCalled()
    expect(wrapper.find('[data-testid="profile-stale"]').text()).toContain(
      'تغيّرت بيانات هذا القسم',
    )
    expect(wrapper.find('[name="employeesCount"]').element.value).toBe('21')
    expect(wrapper.emitted('saved')).toBeUndefined()
    wrapper.unmount()
  })

  it('shows the server field errors and the summary (422)', async () => {
    entitiesService.saveProfileSection.mockRejectedValue(
      failure(422, 'validation_failed', { governanceGrade: ['أدخل نسبة من 0 إلى 100'] }),
    )
    const { wrapper } = await factory()
    await wrapper.find('[name="governanceGrade"]').setValue('99')
    await submit(wrapper)
    expect(wrapper.find('[data-testid="profile-error-summary"]').text()).toContain('درجة الحوكمة')
    expect(wrapper.find('.profile-section__status-text--danger').exists()).toBe(true)
    wrapper.unmount()
  })

  it('blocks an invalid value before calling the API', async () => {
    const { wrapper } = await factory()
    await wrapper.find('[name="governanceGrade"]').setValue('120')
    await submit(wrapper)
    expect(entitiesService.saveProfileSection).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="profile-error-summary"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('reloads the profile when the server refuses the save (403)', async () => {
    entitiesService.saveProfileSection.mockRejectedValue(failure(403, 'forbidden'))
    entitiesService.getMine.mockResolvedValue({ data: profileWith({ canEdit: false }) })
    const { wrapper, store } = await factory()
    await wrapper.find('[name="employeesCount"]').setValue('14')
    await submit(wrapper)
    expect(store.profile.canEdit).toBe(false)
    wrapper.unmount()
  })
})
