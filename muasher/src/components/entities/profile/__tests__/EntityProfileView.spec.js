import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import EntityProfileView from '@/components/entities/profile/EntityProfileView.vue'
import { profileChangesService } from '@/services/profileChangesService'
import { useEntityStore } from '@/stores/entity'
import { createTestGlobals } from '@/test-utils'
import { toOwnEntity } from '@/services/mappers/ownEntity'
import { useConfirm } from '@/composables/useConfirm'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { getMine: vi.fn(), saveProfileSection: vi.fn(), cancelEmailChange: vi.fn() },
}))
vi.mock('@/services/profileChangesService', () => ({
  profileChangesService: { listMine: vi.fn() },
}))

const SECTIONS = [
  {
    key: 'identity',
    revision: 'r-i',
    fields: [
      { key: 'name.ar', requirement: 'required' },
      { key: 'name.en', requirement: 'optional' },
      { key: 'unified_number', requirement: 'required' },
      { key: 'license_number', requirement: 'completion' },
      { key: 'founded_on', requirement: 'completion' },
    ],
  },
  {
    key: 'contact',
    revision: 'r-c',
    fields: [
      { key: 'official_email', requirement: 'required' },
      { key: 'authorized_person_name', requirement: 'required' },
      { key: 'phone', requirement: 'optional' },
      { key: 'website_url', requirement: 'optional' },
      { key: 'marketing_consent', requirement: 'optional' },
    ],
  },
]

function profileWith(overrides = {}) {
  return toOwnEntity({
    id: 6001,
    reference: 'ENT-006001',
    status: 'active',
    name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    unifiedNumber: null,
    licenseNumber: '000123',
    foundedOn: null,
    officialEmail: 'info@namaa.example.sa',
    authorizedPersonName: 'نورة بنت فهد العتيبي',
    phone: '+966501234567',
    phoneVerified: false,
    websiteUrl: null,
    marketingConsent: false,
    pendingEmailChange: null,
    createdAt: '2026-07-02T08:40:00Z',
    verifiedAt: '2026-07-02T08:46:00Z',
    canEdit: true,
    profileSections: SECTIONS,
    profileCompletion: {
      percent: 60,
      counted: 15,
      completedCount: 9,
      sections: [
        {
          key: 'identity',
          state: 'incomplete',
          missingCount: 2,
          missingFields: ['unified_number', 'founded_on'],
        },
        { key: 'contact', state: 'complete', missingCount: 0, missingFields: [] },
      ],
    },
    ...overrides,
  })
}

const CERTIFICATE = { id: 'c-1', number: 'CRT-2026-000003', validUntil: '2027-09-04' }

async function factory(profile = profileWith(), certificate = CERTIFICATE) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  useEntityStore().profile = profile
  await globals.router.push('/entity/profile')
  const wrapper = mount(EntityProfileView, {
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
    props: {
      profile,
      tier: 'silver',
      certificate,
      regionOptions: [],
      cityOptionsFor: () => [],
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  profileChangesService.listMine.mockResolvedValue({
    data: [
      {
        id: 1,
        field: 'phone',
        section: 'contact',
        fieldLabel: { ar: 'رقم الجوال', en: 'Mobile number' },
        oldValue: null,
        newValue: '+9665*****567',
        changedBy: 'staff',
        changedAt: '2026-11-02T10:05:00Z',
      },
    ],
    meta: { pagination: { page: 1, perPage: 10, total: 1, lastPage: 1 } },
  })
})

describe('EntityProfileView', () => {
  it('shows the identity header with the compact facts and the eye button', async () => {
    const wrapper = await factory()
    expect(wrapper.find('.profile-header__name').text()).toBe('مؤسسة نماء للتنمية')
    expect(wrapper.find('[data-testid="profile-account-status"]').text()).toBe('الحساب نشط')
    expect(wrapper.find('.profile-facts').text()).toContain('ENT-006001')
    expect(wrapper.find('[data-testid="profile-certificate"]').text()).toContain('صالحة حتى')
    const eye = wrapper.find('[data-testid="profile-view-certificate"]')
    expect(eye.attributes('aria-label')).toBe('عرض الشهادة')
    expect(eye.attributes('href')).toBe('/certificates')
    wrapper.unmount()
  })

  it('hides the certificate cell when there is no certificate', async () => {
    const wrapper = await factory(profileWith(), null)
    expect(wrapper.find('[data-testid="profile-certificate"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('marks counted empty fields with icon + text + «أضِف» and optional ones with «—»', async () => {
    const wrapper = await factory()
    const identity = wrapper.find('[data-section="identity"]')
    const missing = identity.findAll('[data-testid="profile-missing"]')
    expect(missing).toHaveLength(2)
    expect(missing[0].find('[data-icon="warning-triangle"]').exists()).toBe(true)
    expect(missing[0].text()).toContain('غير مُدخل')
    expect(missing[0].find('.profile-missing__add').attributes('aria-label')).toBe(
      'أضِف الرقم الوطني الموحد',
    )
    const website = wrapper.find('[data-section="contact"]').findAll('.profile-field')[3]
    expect(website.find('.profile-field__empty').text()).toContain('—')
    expect(website.find('[data-testid="profile-missing"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('opens the section in edit mode focused on the field from «أضِف»', async () => {
    const wrapper = await factory()
    await wrapper.find('[data-field="foundedOn"] .profile-missing__add').trigger('click')
    await flushPromises()
    await flushPromises()
    expect(wrapper.find('[data-section="identity"] form').exists()).toBe(true)
    expect(document.activeElement?.getAttribute('name')).toBe('foundedOn')
    wrapper.unmount()
  })

  it('guards unsaved changes when another section is opened, naming the section', async () => {
    const wrapper = await factory()
    const { confirmState, dismiss } = useConfirm()
    await wrapper.find('[data-section="identity"] .profile-section__edit').trigger('click')
    await flushPromises()
    await wrapper.find('[name="licenseNumber"]').setValue('000999')
    await flushPromises()
    await wrapper.find('[data-section="contact"] .profile-section__edit').trigger('click')
    await flushPromises()
    expect(confirmState.isOpen).toBe(true)
    expect(confirmState.message).toBe('ستفقد تغييرات «الهوية والتسجيل».')
    dismiss()
    await flushPromises()
    expect(wrapper.find('[data-section="identity"] form').exists()).toBe(true)
    expect(wrapper.find('[data-section="contact"] form').exists()).toBe(false)
    wrapper.unmount()
  })

  it('lists sections only in the completeness card and jumps to the first missing field', async () => {
    const wrapper = await factory()
    const card = wrapper.find('[data-testid="profile-completeness"]')
    expect(card.findAll('.profile-completeness__row')).toHaveLength(2)
    expect(card.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('60')
    await card.find('[data-testid="profile-complete-action"]').trigger('click')
    await flushPromises()
    await flushPromises()
    expect(document.activeElement?.getAttribute('name')).toBe('unifiedNumber')
    wrapper.unmount()
  })

  it('shows the phone as unverified with «وثّق الرقم» only when the profile is editable', async () => {
    const editable = await factory()
    const phone = editable.find('[data-testid="profile-phone"]')
    expect(phone.text()).toContain('050 123 4567')
    expect(phone.text()).toContain('غير موثّق')
    expect(phone.find('.profile-field__action').text()).toBe('وثّق الرقم')
    editable.unmount()
    const locked = await factory(profileWith({ canEdit: false, status: 'deactivated' }))
    expect(locked.find('[data-testid="profile-phone"] .profile-field__action').exists()).toBe(false)
    locked.unmount()
  })

  it('renders the read-only state with one banner and no edit action', async () => {
    const wrapper = await factory(profileWith({ canEdit: false, status: 'deactivated' }))
    expect(wrapper.find('[data-testid="profile-read-only"]').text()).toBe(
      'الملف للعرض فقط لأن حساب جهتك موقوف مؤقتًا.',
    )
    expect(wrapper.findAll('.profile-section__edit')).toHaveLength(0)
    expect(wrapper.findAll('.profile-section__locked')).toHaveLength(2)
    expect(wrapper.findAll('.profile-missing__add')).toHaveLength(0)
    expect(wrapper.find('[data-testid="profile-change-logo"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="email-change-open"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows a pending e-mail change with its target and the cancel action', async () => {
    const wrapper = await factory(
      profileWith({
        pendingEmailChange: {
          status: 'awaiting-approval',
          newEmail: 'n***@namaa.example.sa',
          policy: 'requires_approval',
          requestedAt: '2026-11-09T08:00:00Z',
        },
      }),
    )
    const email = wrapper.find('[data-testid="profile-official-email"]')
    expect(email.text()).toContain('n***@namaa.example.sa')
    expect(email.find('[data-testid="email-change-cancel"]').exists()).toBe(true)
    expect(email.find('[data-testid="email-change-open"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('opens the history drawer filtered by the section and labels staff by kind', async () => {
    const wrapper = await factory()
    await wrapper.find('[data-section="contact"] .profile-section__history').trigger('click')
    await flushPromises()
    expect(profileChangesService.listMine).toHaveBeenCalledWith(
      expect.objectContaining({ section: 'contact' }),
    )
    const drawer = wrapper.find('[data-testid="profile-history-drawer"]')
    expect(drawer.find('[aria-pressed="true"]').text()).toBe('التواصل والمسؤول عن الحساب')
    expect(drawer.text()).toContain('موظف المنصة')
    expect(drawer.text()).toContain('+9665*****567')
    await drawer.findAll('.history-drawer__chip').at(-1).trigger('click')
    await flushPromises()
    expect(profileChangesService.listMine).toHaveBeenLastCalledWith(
      expect.not.objectContaining({ section: expect.anything() }),
    )
    wrapper.unmount()
  })
})
