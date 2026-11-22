import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomepageContent from '@/views/settings/HomepageContent.vue'
import ItemFormDialog from '@/components/cms/ItemFormDialog.vue'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import { cmsService } from '@/services/cmsService'
import { siteSettingsService } from '@/services/siteSettingsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/cmsService', () => ({
  cmsService: {
    getPage: vi.fn(),
    createItem: vi.fn(),
    updateItem: vi.fn(),
    uploadImage: vi.fn(),
    uploadLogo: vi.fn(),
  },
}))
vi.mock('@/services/siteSettingsService', () => ({
  siteSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const PUBLISHED_AT = '2026-11-18T13:49:00Z'
const LOGO = { fileId: 'b4c1a2d3-0000-4000-8000-000000000095', url: '/api/v1/content/files/logo' }

function partner(id, order, overrides = {}) {
  return {
    id,
    sectionId: 54,
    enabled: true,
    order,
    title: { ar: `شريك ${id}`, en: `Partner ${id}` },
    subtitle: null,
    description: null,
    ctaLabel: null,
    ctaUrl: null,
    icon: null,
    image: null,
    meta: null,
    updatedAt: '2026-11-01T08:00:00Z',
    ...overrides,
  }
}

function partnersSection(overrides = {}) {
  return {
    id: 54,
    page: 'home',
    key: 'partners',
    type: 'partners',
    enabled: true,
    order: 1,
    title: { ar: 'شركاء النجاح', en: 'Success partners' },
    subtitle: null,
    description: null,
    ctaLabel: null,
    ctaUrl: null,
    icon: null,
    background: null,
    image: null,
    meta: null,
    items: [partner(95, 1, { image: LOGO }), partner(96, 2)],
    updatedAt: '2026-11-01T08:00:00Z',
    ...overrides,
  }
}

function homePage(overrides = {}) {
  return {
    key: 'home',
    title: { ar: 'الرئيسية', en: 'Home' },
    published: true,
    publishedAt: PUBLISHED_AT,
    hasUnpublishedChanges: true,
    contentUpdatedAt: '2026-11-21T23:00:00Z',
    seo: { title: null, description: null },
    sections: [partnersSection({ updatedAt: '2026-11-21T23:00:00Z' })],
    ...overrides,
  }
}

async function openPartners(page = homePage()) {
  cmsService.getPage.mockResolvedValue({ data: page })
  const wrapper = mount(HomepageContent, {
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  await flushPromises()
  const itemsButton = wrapper
    .findAll('.cms-section-row__actions button')
    .find((button) => button.text().includes('Items'))
  await itemsButton.trigger('click')
  await flushPromises()
  return wrapper
}

function inBody(selector) {
  return document.body.querySelector(selector)
}

function rows() {
  return [...document.body.querySelectorAll('.cms-items__row')]
}

function formText() {
  return [...document.body.querySelectorAll('.modal')].at(-1).textContent
}

async function openPartnerForm(wrapper, rowIndex = null) {
  const button =
    rowIndex === null
      ? inBody('.cms-items__footer .btn--primary')
      : rows()[rowIndex].querySelector('.btn--secondary')
  button.click()
  await flushPromises()
  return wrapper.findComponent(ItemFormDialog)
}

function field(dialog, name) {
  return dialog
    .findAllComponents(BaseInput)
    .find((input) => input.props('name') === name)
    .find('input')
}

async function chooseLogo(dialog, file) {
  const input = dialog.findComponent(BaseFileUpload).find('input[type="file"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

async function submit(dialog) {
  dialog.findComponent(FormModal).vm.$emit('submit')
  await flushPromises()
}

afterEach(() => {
  document.body.innerHTML = ''
})

beforeEach(() => {
  vi.clearAllMocks()
  siteSettingsService.get.mockResolvedValue({ data: {} })
})

describe('Success partners in the content editor', () => {
  it('says that edits are drafts until publishing, and which partners have no logo', async () => {
    const wrapper = await openPartners()
    expect(wrapper.find('.cms-section-row').text()).toContain('Changed since last publish')
    const draft = inBody('.cms-items__draft')
    expect(draft.getAttribute('role')).toBe('note')
    expect(draft.textContent).toContain('do not appear on the site until you press “Publish”')
    expect(draft.textContent).toContain('Unpublished changes')
    const [withLogo, withoutLogo] = rows()
    expect(withLogo.querySelector('img.cms-items__logo').getAttribute('src')).toBe(LOGO.url)
    expect(withLogo.textContent).not.toContain('No logo')
    expect(withoutLogo.querySelector('.badge--warning').textContent).toBe('No logo — not shown')
    expect(inBody('.cms-items__footer .btn--primary').textContent.trim()).toBe('Add partner')
    wrapper.unmount()
  })

  it('hides the change markers once everything is published', async () => {
    const page = homePage({ hasUnpublishedChanges: false, sections: [partnersSection()] })
    const wrapper = await openPartners(page)
    expect(wrapper.find('.cms-section-row').text()).not.toContain('Changed since last publish')
    expect(inBody('.cms-items__draft').textContent).not.toContain('Unpublished changes')
    wrapper.unmount()
  })

  it('asks only for the names, the logo and an optional link', async () => {
    const wrapper = await openPartners()
    const dialog = await openPartnerForm(wrapper)
    expect(dialog.findComponent(FormModal).props('title')).toBe('Add partner')
    const names = dialog.findAllComponents(BaseInput).map((input) => input.props('name'))
    expect(names).toEqual(['title.ar', 'title.en', 'ctaUrl'])
    const upload = dialog.findComponent(BaseFileUpload)
    expect(upload.find('input[type="file"]').attributes('accept')).toContain('.svg')
    expect(formText()).toContain('2:1 to 3:1, at least 400 px wide')
    expect(formText()).not.toContain('Icon')
    expect(formText()).not.toContain('Button')
    wrapper.unmount()
  })

  it('refuses a partner without both names, without a logo or with a link that is not https', async () => {
    const wrapper = await openPartners()
    const dialog = await openPartnerForm(wrapper)
    await field(dialog, 'title.ar').setValue('وزارة الصحة')
    await field(dialog, 'ctaUrl').setValue('http://www.moh.gov.sa')
    await submit(dialog)
    await vi.waitFor(() => expect(formText()).toContain('This field is required'))
    expect(formText()).toContain("Upload the partner's logo.")
    expect(formText()).toContain('The link must be a full link starting with https://.')
    expect(cmsService.createItem).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('uploads the logo through the logo endpoint and saves the partner fields only', async () => {
    cmsService.uploadLogo.mockResolvedValue({ data: { fileId: 'new-logo', url: '/logo.svg' } })
    cmsService.createItem.mockResolvedValue({ data: partnersSection() })
    const wrapper = await openPartners()
    const dialog = await openPartnerForm(wrapper)
    await field(dialog, 'title.ar').setValue(' وزارة الصحة ')
    await field(dialog, 'title.en').setValue('Ministry of Health')
    await field(dialog, 'ctaUrl').setValue('https://www.moh.gov.sa')
    await chooseLogo(dialog, new File(['<svg/>'], 'moh.svg', { type: 'image/svg+xml' }))
    await submit(dialog)
    await vi.waitFor(() => expect(cmsService.createItem).toHaveBeenCalled())
    expect(cmsService.uploadLogo).toHaveBeenCalledOnce()
    expect(cmsService.uploadImage).not.toHaveBeenCalled()
    expect(cmsService.createItem).toHaveBeenCalledWith(54, {
      title: { ar: 'وزارة الصحة', en: 'Ministry of Health' },
      ctaUrl: 'https://www.moh.gov.sa',
      imageFileId: 'new-logo',
    })
    wrapper.unmount()
  })

  it('refuses a logo of another type or over 1 MB before uploading it', async () => {
    const wrapper = await openPartners()
    const dialog = await openPartnerForm(wrapper)
    await chooseLogo(dialog, new File(['gif'], 'logo.gif', { type: 'image/gif' }))
    expect(formText()).toContain('Use a PNG, JPG or SVG logo.')
    const large = new File([new Uint8Array(1024 * 1024 + 1)], 'logo.png', { type: 'image/png' })
    await chooseLogo(dialog, large)
    expect(formText()).toContain('The logo is larger than 1 MB.')
    expect(cmsService.uploadLogo).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('keeps the stored logo when only the name of a partner changes', async () => {
    cmsService.updateItem.mockResolvedValue({ data: partnersSection() })
    const wrapper = await openPartners()
    const dialog = await openPartnerForm(wrapper, 0)
    expect(dialog.findComponent(FormModal).props('title')).toBe('Edit partner')
    await field(dialog, 'title.en').setValue('Partner 95 renamed')
    await submit(dialog)
    await vi.waitFor(() => expect(cmsService.updateItem).toHaveBeenCalled())
    expect(cmsService.updateItem).toHaveBeenCalledWith(54, 95, {
      title: { ar: 'شريك 95', en: 'Partner 95 renamed' },
      ctaUrl: null,
    })
    wrapper.unmount()
  })
})
