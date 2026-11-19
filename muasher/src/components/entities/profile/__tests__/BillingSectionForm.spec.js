import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ProfileSectionForm from '@/components/entities/profile/ProfileSectionForm.vue'
import ProfileSectionRead from '@/components/entities/profile/ProfileSectionRead.vue'
import { entitiesService } from '@/services/entitiesService'
import { useEntityStore } from '@/stores/entity'
import { createTestGlobals } from '@/test-utils'
import { toOwnEntity } from '@/services/mappers/ownEntity'
import { sectionViewsOf } from '@/utils/entityProfile/sections'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: {
    getMine: vi.fn(),
    saveProfileSection: vi.fn(),
    uploadVatCertificate: vi.fn(),
    removeVatCertificate: vi.fn(),
    requestVatCertificateDownload: vi.fn(),
  },
}))

const FIELDS = [
  ['address_building_number', 'required'],
  ['address_street', 'required'],
  ['address_district', 'required'],
  ['address_city', 'required'],
  ['address_postal_code', 'required'],
  ['address_additional_number', 'required'],
  ['address_short_code', 'optional'],
  ['vat_registered', 'required'],
  ['vat_number', 'required'],
  ['vat_certificate', 'required'],
]

function profileWith(overrides = {}) {
  return toOwnEntity({
    addressBuildingNumber: '2154',
    addressStreet: 'شارع الأمير سلطان',
    addressDistrict: 'حي الروضة',
    addressCity: 'جدة',
    addressPostalCode: '23435',
    addressAdditionalNumber: '8027',
    addressShortCode: null,
    vatRegistered: false,
    vatNumber: null,
    vatCertificate: null,
    canEdit: true,
    profileSections: [
      {
        key: 'billing',
        revision: 'r-billing',
        fields: FIELDS.map(([key, requirement]) => ({ key, requirement })),
      },
    ],
    profileCompletion: { sections: [{ key: 'billing', state: 'complete', missingFields: [] }] },
    ...overrides,
  })
}

async function settle() {
  for (let round = 0; round < 4; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

function mountWith(component, profile) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  const store = useEntityStore()
  store.profile = profile
  return mount(component, {
    global: globals,
    attachTo: document.body,
    props: {
      section: sectionViewsOf(store.profile)[0],
      profile: store.profile,
      cityOptionsFor: () => [],
    },
  })
}

describe('billing section', () => {
  it('asks the VAT question and reveals the VAT number and certificate only after «yes»', async () => {
    const wrapper = mountWith(ProfileSectionForm, profileWith())
    await flushPromises()
    expect(wrapper.text()).toContain('هل جهتك مسجّلة في ضريبة القيمة المضافة؟')
    expect(wrapper.find('input[name="vatNumber"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="vat-certificate-upload"]').exists()).toBe(false)

    await wrapper.get('input[name="vatRegistered"][value="yes"]').setValue(true)
    await flushPromises()
    expect(wrapper.find('input[name="vatNumber"]').exists()).toBe(true)
    const upload = wrapper.get('[data-testid="vat-certificate-upload"]')
    expect(upload.text()).toContain('احفظ الإجابة «نعم» والرقم الضريبي أولًا')
    expect(upload.get('.file-upload__dropzone').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('sends the address with the yes answer and the VAT number', async () => {
    entitiesService.saveProfileSection.mockResolvedValueOnce({
      data: profileWith({ vatRegistered: true }),
      meta: {},
    })
    const wrapper = mountWith(ProfileSectionForm, profileWith())
    await flushPromises()
    await wrapper.get('input[name="vatRegistered"][value="yes"]').setValue(true)
    await flushPromises()
    await wrapper.get('input[name="vatNumber"]').setValue('300060010000003')
    await wrapper.get('form').trigger('submit')
    await settle()
    expect(entitiesService.saveProfileSection).toHaveBeenCalledWith(
      'billing',
      expect.objectContaining({
        baseRevision: 'r-billing',
        addressBuildingNumber: '2154',
        vatRegistered: true,
        vatNumber: '300060010000003',
      }),
    )
    wrapper.unmount()
  })

  it('shows the uploaded certificate with a download action in the read view', () => {
    const wrapper = mountWith(
      ProfileSectionRead,
      profileWith({
        vatRegistered: true,
        vatNumber: '300060010000003',
        vatCertificate: { id: 'f-1', name: 'vat.pdf', sizeBytes: 1200, uploadedAt: null },
      }),
    )
    expect(wrapper.text()).toContain('300060010000003')
    expect(wrapper.get('[data-field="vatCertificate"]').text()).toContain('vat.pdf')
    expect(wrapper.text()).toContain('تنزيل الشهادة')
    wrapper.unmount()
  })

  it('hides the VAT rows from the read view of a non-registered entity', () => {
    const wrapper = mountWith(ProfileSectionRead, profileWith())
    expect(wrapper.find('[data-field="vatNumber"]').exists()).toBe(false)
    expect(wrapper.find('[data-field="vatCertificate"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
