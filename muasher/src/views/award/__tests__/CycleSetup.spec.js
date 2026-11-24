import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CycleSetup from '@/views/award/CycleSetup.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { useAuthStore } from '@/stores/auth'
import { formatHijriDate, startOfRiyadhDay } from '@/utils/datetime'

const service = vi.hoisted(() => ({
  get: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  getDefaultConditions: vi.fn(),
  listCandidates: vi.fn(),
  syncMembers: vi.fn(),
  syncInitialAssessors: vi.fn(),
}))
const confirm = vi.hoisted(() => vi.fn())

vi.mock('@/services/awardCycleService', () => ({ awardCycleService: service }))
vi.mock('@/services/regionsService', () => ({
  regionsService: {
    listRegions: vi.fn().mockResolvedValue({
      data: [
        { id: 1, code: '01', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } },
        { id: 2, code: '02', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' } },
      ],
    }),
  },
}))
vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: {
    getPublishedCriteria: vi.fn().mockResolvedValue({ data: { versionId: 4, versionNumber: 3 } }),
  },
}))
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm }) }))

const ASSESSOR = { id: 10, name: 'مقيّم أولي', isActive: true }

function detail(overrides = {}) {
  return {
    id: 32,
    name: { ar: 'الدورة القادمة', en: 'Next cycle' },
    description: null,
    year: 2027,
    status: 'draft',
    criteriaVersionId: 4,
    scaleKind: null,
    submissionStart: '2027-01-31T21:00:00Z',
    submissionEnd: '2027-03-15T20:59:00Z',
    committeeStart: '2027-03-31T21:00:00Z',
    committeeEnd: '2027-05-15T20:59:00Z',
    announcementAt: null,
    conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
    initialReviewSlaDays: 14,
    participationCertificateEnabled: false,
    entryFee: '750.00',
    entryFeeEditable: true,
    regions: [
      { id: 1, code: '01', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } },
      { id: 2, code: '02', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' } },
    ],
    lockedRegionIds: [],
    canManage: true,
    thresholdLocked: false,
    openBlock: null,
    revealsStaff: true,
    members: [],
    initialAssessors: [],
    updatedAt: '2026-11-23T10:00:00Z',
    ...overrides,
  }
}

async function mountSetup(path) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
  await globals.router.push(path)
  const wrapper = mount(CycleSetup, {
    global: { plugins: globals.plugins, directives: globals.directives },
    attachTo: document.body,
  })
  await flushPromises()
  return { wrapper, router: globals.router }
}

function fieldOf(wrapper, name) {
  return wrapper.findAll('.field').find((candidate) => candidate.find(`[name="${name}"]`).exists())
}

function hintOf(wrapper, name) {
  return fieldOf(wrapper, name).find('.field__hint').element.textContent
}

function lockStateOf(wrapper, name) {
  const field = fieldOf(wrapper, name)
  return [...field.findAll('.datepicker__segment'), field.find('.datepicker__calendar')].map(
    (control) => control.attributes('disabled') !== undefined,
  )
}

function stepForm(wrapper, step) {
  return wrapper.find(`form[data-step="${step}"]`)
}

async function submitStep(wrapper) {
  await wrapper.find('form.cycle-setup__form').trigger('submit')
  await flushPromises()
}

async function setDate(wrapper, name, value) {
  await wrapper.find(`.datepicker__segment[name="${name}"]`).setValue(value)
}

beforeEach(() => {
  vi.clearAllMocks()
  service.listCandidates.mockResolvedValue({ data: [] })
  service.getDefaultConditions.mockResolvedValue({
    data: {
      minEntityAgeYears: 3,
      disqualificationScore: '60.00',
      initialReviewSlaDays: 14,
      entryFee: '0.00',
    },
  })
  confirm.mockResolvedValue(false)
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('CycleSetup — create (R-4)', () => {
  it('walks the steps in order, keeps an invalid step open and creates the draft after the dates', async () => {
    service.create.mockResolvedValue({ data: detail() })
    const { wrapper, router } = await mountSetup('/admin/award/cycles/new')
    const buttons = wrapper.findAll('.wizard__button')
    expect(buttons).toHaveLength(7)
    expect(buttons.slice(1).every((button) => button.attributes('disabled') !== undefined)).toBe(
      true,
    )
    expect(wrapper.find('.cycle-setup__counter').exists()).toBe(false)
    expect(wrapper.find('legend .cycle-setup__step-counter').text()).toBe('(الخطوة 1 من 7)')

    await submitStep(wrapper)
    await vi.waitFor(() => expect(wrapper.find('.field__error').exists()).toBe(true))
    expect(stepForm(wrapper, 'identity').exists()).toBe(true)
    expect(service.create).not.toHaveBeenCalled()

    await wrapper.find('input[name="name.ar"]').setValue('الدورة القادمة')
    await wrapper.find('input[name="name.en"]').setValue('Next cycle')
    await submitStep(wrapper)
    await vi.waitFor(() => expect(stepForm(wrapper, 'dates').exists()).toBe(true))
    expect(service.create).not.toHaveBeenCalled()

    await setDate(wrapper, 'submissionStart', '2027-02-01')
    await setDate(wrapper, 'submissionEnd', '2027-03-15')
    await setDate(wrapper, 'committeeStart', '2027-04-01')
    await setDate(wrapper, 'committeeEnd', '2027-05-15')
    await submitStep(wrapper)

    await vi.waitFor(() => expect(service.create).toHaveBeenCalledTimes(1))
    const payload = service.create.mock.calls[0][0]
    expect(payload).toMatchObject({
      name: { ar: 'الدورة القادمة', en: 'Next cycle' },
      submissionStart: '2027-01-31T21:00:00.000Z',
      committeeEnd: '2027-05-15T20:59:00.000Z',
    })
    expect(payload).not.toHaveProperty('criteriaVersionId')
    expect(payload).not.toHaveProperty('regionIds')
    expect(payload).not.toHaveProperty('description')
    await vi.waitFor(() => expect(stepForm(wrapper, 'conditions').exists()).toBe(true))
    await vi.waitFor(() =>
      expect(router.currentRoute.value.fullPath).toBe('/admin/award/cycles/32/setup'),
    )
    expect(wrapper.find('.dropdown-select-all__count').text()).toBe('2 من 2 منطقة')
    expect(wrapper.find('[data-testid="cycle-criteria-fact"]').exists()).toBe(false)
  })

  it('asks for the English description when only the Arabic one is written', async () => {
    const { wrapper } = await mountSetup('/admin/award/cycles/new')
    await wrapper.find('input[name="name.ar"]').setValue('الدورة القادمة')
    await wrapper.find('input[name="name.en"]').setValue('Next cycle')
    await wrapper.find('textarea[name="description.ar"]').setValue('تعريف الدورة')
    await submitStep(wrapper)
    await vi.waitFor(() =>
      expect(wrapper.text()).toContain('اكتبوا التعريف باللغتين، أو اتركوهما فارغين.'),
    )
    expect(stepForm(wrapper, 'identity').exists()).toBe(true)
  })
})

describe('CycleSetup — edit', () => {
  it('opens at the team while no active initial assessor is linked, otherwise at the review', async () => {
    service.get.mockResolvedValue({ data: detail() })
    const draft = await mountSetup('/admin/award/cycles/32/setup')
    expect(draft.wrapper.find('.cycle-staff').exists()).toBe(true)
    expect(
      draft.wrapper.findAll('.wizard__button').every((button) => !button.attributes('disabled')),
    ).toBe(true)
    draft.wrapper.unmount()

    service.get.mockResolvedValue({ data: detail({ initialAssessors: [ASSESSOR] }) })
    const ready = await mountSetup('/admin/award/cycles/32/setup')
    expect(ready.wrapper.find('.cycle-summary').exists()).toBe(true)
    expect(ready.wrapper.find('[data-testid="cycle-save-draft"]').exists()).toBe(true)
  })

  it('saves the team with the step action, only what changed, and moves on', async () => {
    service.get.mockResolvedValue({ data: detail() })
    service.syncInitialAssessors.mockResolvedValue({
      data: detail({ initialAssessors: [ASSESSOR], updatedAt: '2026-11-23T11:00:00Z' }),
    })
    const { wrapper } = await mountSetup('/admin/award/cycles/32/setup')
    expect(wrapper.find('.cycle-staff').exists()).toBe(true)
    expect(wrapper.find('.cycle-staff button.btn--secondary').exists()).toBe(false)
    expect(wrapper.find('.cycle-staff .cycle-setup__step-counter').text()).toBe('(الخطوة 6 من 7)')
    wrapper.findAllComponents(AppCombobox).at(-1).vm.$emit('update:modelValue', [10])
    await flushPromises()
    await wrapper.find('.cycle-setup__next').trigger('click')
    await vi.waitFor(() =>
      expect(service.syncInitialAssessors).toHaveBeenCalledWith(32, { assessorIds: [10] }),
    )
    expect(service.syncMembers).not.toHaveBeenCalled()
    await vi.waitFor(() => expect(wrapper.find('.cycle-summary').exists()).toBe(true))
  })

  it('stops on a refused team change and shows the conflict on its field', async () => {
    service.get.mockResolvedValue({ data: detail() })
    const conflict = new Error('sod_conflict')
    conflict.normalized = {
      status: 422,
      code: 'sod_conflict',
      message: 'تعارض في فصل المهام.',
      fieldErrors: { 'awardSecretaryIds.0': ['لا يجمع الشخص بين الدورين في الدورة نفسها.'] },
    }
    service.syncMembers.mockRejectedValue(conflict)
    const { wrapper } = await mountSetup('/admin/award/cycles/32/setup')
    const comboboxes = wrapper.findAllComponents(AppCombobox)
    comboboxes.at(1).vm.$emit('update:modelValue', [10])
    comboboxes.at(-1).vm.$emit('update:modelValue', [10])
    await flushPromises()
    await wrapper.find('.cycle-setup__next').trigger('click')
    await vi.waitFor(() => expect(service.syncMembers).toHaveBeenCalledTimes(1))
    await flushPromises()
    expect(service.syncInitialAssessors).not.toHaveBeenCalled()
    expect(wrapper.find('.cycle-staff').text()).toContain(
      'لا يجمع الشخص بين الدورين في الدورة نفسها.',
    )
    await wrapper.findAll('.wizard__button')[0].trigger('click')
    await flushPromises()
    expect(confirm).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.cycle-staff').exists()).toBe(true)
  })

  it('saves only the changed fields of the step and moves on', async () => {
    service.get.mockResolvedValue({ data: detail({ initialAssessors: [ASSESSOR] }) })
    service.update.mockResolvedValue({
      data: detail({
        initialAssessors: [ASSESSOR],
        conditions: { minEntityAgeYears: 5, disqualificationScore: '60.00' },
        updatedAt: '2026-11-23T11:00:00Z',
      }),
    })
    const { wrapper } = await mountSetup('/admin/award/cycles/32/setup')
    await wrapper.findAll('.wizard__button')[2].trigger('click')
    await flushPromises()
    await wrapper.find('input[name="minEntityAgeYears"]').setValue('5')
    await submitStep(wrapper)
    await vi.waitFor(() =>
      expect(service.update).toHaveBeenCalledWith(32, { minEntityAgeYears: 5 }),
    )
    await vi.waitFor(() => expect(stepForm(wrapper, 'deadlines').exists()).toBe(true))
  })

  it('keeps the step open when the unsaved edits are not discarded', async () => {
    service.get.mockResolvedValue({ data: detail({ initialAssessors: [ASSESSOR] }) })
    const { wrapper } = await mountSetup('/admin/award/cycles/32/setup')
    await wrapper.findAll('.wizard__button')[0].trigger('click')
    await flushPromises()
    await wrapper.find('input[name="year"]').setValue('2028')
    await flushPromises()
    await wrapper.findAll('.wizard__button')[1].trigger('click')
    await flushPromises()
    expect(confirm).toHaveBeenCalledTimes(1)
    expect(stepForm(wrapper, 'identity').exists()).toBe(true)
  })

  it('shows the locked fields of a running cycle read only with the reason', async () => {
    service.get.mockResolvedValue({
      data: detail({
        status: 'submission-open',
        initialAssessors: [ASSESSOR],
        lockedRegionIds: [1],
        entryFeeEditable: false,
      }),
    })
    const { wrapper } = await mountSetup('/admin/award/cycles/32/setup')
    await wrapper.findAll('.wizard__button')[1].trigger('click')
    await flushPromises()
    expect(lockStateOf(wrapper, 'submissionStart')).toEqual([true, true, true, true])
    expect(lockStateOf(wrapper, 'submissionEnd')).toEqual([false, false, false, false])
    await fieldOf(wrapper, 'submissionStart').find('.datepicker__calendar').trigger('click')
    expect(wrapper.find('.date-calendar').exists()).toBe(false)
    expect(wrapper.find('[data-testid="cycle-lock-notes"]').text()).toContain(
      'بعض الحقول لا تُعدَّل إلا قبل فتح التقديم.',
    )
    expect(hintOf(wrapper, 'submissionStart')).toBe(
      `مقفل في المرحلة الحالية من الدورة\n${formatHijriDate(startOfRiyadhDay('2027-02-01'), 'ar')}`,
    )
    expect(hintOf(wrapper, 'submissionEnd')).toBe(
      formatHijriDate(startOfRiyadhDay('2027-03-15'), 'ar'),
    )
    expect(wrapper.find('.cycle-setup__timeline').exists()).toBe(false)
    await wrapper.findAll('.wizard__button')[2].trigger('click')
    await flushPromises()
    expect(stepForm(wrapper, 'conditions').exists()).toBe(true)
    expect(wrapper.find('.dropdown-tag.is-locked').text()).toContain('منطقة الرياض')
  })
})
