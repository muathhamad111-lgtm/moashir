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
  entitiesService: {
    getMine: vi.fn(),
    saveProfileSection: vi.fn(),
    uploadOwnDocument: vi.fn(),
    requestOwnDocumentDownload: vi.fn(),
  },
}))

vi.mock('@/services/profileChangeRequestsService', () => ({
  profileChangeRequestsService: { ownPending: vi.fn().mockResolvedValue({ data: null }) },
}))

function contactProfile(pendingItems = null) {
  return toOwnEntity({
    name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity' },
    authorizedPersonName: 'أحمد بن سالم القحطاني',
    phone: null,
    canEdit: true,
    pendingProfileChange: pendingItems ? { id: 'p-1', revision: 1, items: pendingItems } : null,
    profileSections: [
      {
        key: 'contact',
        revision: 'r-1',
        fields: [
          { key: 'authorized_person_name', requirement: 'required' },
          { key: 'phone', requirement: 'optional' },
        ],
      },
    ],
    profileCompletion: { percent: 100, counted: 1, completedCount: 1, sections: [] },
  })
}

async function mountContact(pendingItems = null) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  const store = useEntityStore()
  store.profile = contactProfile(pendingItems)
  const wrapper = mount(ProfileSectionForm, {
    global: globals,
    attachTo: document.body,
    props: {
      section: sectionViewsOf(store.profile)[0],
      profile: store.profile,
      cityOptionsFor: () => [],
    },
  })
  await flushPromises()
  return { wrapper, store }
}

async function settle() {
  for (let round = 0; round < 4; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

beforeEach(() => vi.clearAllMocks())

describe('the authorization letter rule of the contact section', () => {
  it('asks for a new letter only when the authorized person changes, and blocks the save without it', async () => {
    const { wrapper } = await mountContact()
    expect(wrapper.find('[data-testid="authorization-letter-field"]').exists()).toBe(false)

    await wrapper.find('[name="authorizedPersonName"]').setValue('ريم بنت خالد الحربي')
    expect(wrapper.find('[data-testid="authorization-letter-field"]').exists()).toBe(true)
    await wrapper.find('form').trigger('submit')
    await settle()

    expect(entitiesService.saveProfileSection).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(
      'Upload the new authorization letter before saving the authorized person.',
    )
  })

  it('uploads the letter into the pending change and then saves the new person', async () => {
    const { wrapper, store } = await mountContact()
    entitiesService.uploadOwnDocument.mockResolvedValue({
      data: {
        ...store.profile,
        pendingProfileChange: { id: 'p-1', revision: 1, items: ['authorization_letter'] },
      },
    })
    entitiesService.saveProfileSection.mockResolvedValue({ data: store.profile, meta: {} })
    await wrapper.find('[name="authorizedPersonName"]').setValue('ريم بنت خالد الحربي')
    const input = wrapper.find('input[type="file"][name="authorizationLetter"]')
    const letter = new File(['%PDF-1.4'], 'letter.pdf', { type: 'application/pdf' })
    Object.defineProperty(input.element, 'files', { value: [letter], configurable: true })
    await input.trigger('change')
    await settle()

    expect(entitiesService.uploadOwnDocument).toHaveBeenCalledWith('authorizationLetter', letter)
    expect(wrapper.text()).toContain(
      'The new authorization letter is attached to the change request.',
    )
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(entitiesService.saveProfileSection).toHaveBeenCalledWith(
      'contact',
      expect.objectContaining({ authorizedPersonName: 'ريم بنت خالد الحربي' }),
    )
  })

  it('refuses a letter that is not a PDF before uploading it', async () => {
    const { wrapper } = await mountContact()
    await wrapper.find('[name="authorizedPersonName"]').setValue('ريم بنت خالد الحربي')
    const input = wrapper.find('input[type="file"][name="authorizationLetter"]')
    const image = new File(['png'], 'letter.png', { type: 'image/png' })
    Object.defineProperty(input.element, 'files', { value: [image], configurable: true })
    await input.trigger('change')
    await settle()
    expect(entitiesService.uploadOwnDocument).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Upload the document as a PDF only.')
  })
})
