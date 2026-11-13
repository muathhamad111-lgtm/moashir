import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomepageContent from '@/views/settings/HomepageContent.vue'
import SectionFormDialog from '@/components/cms/SectionFormDialog.vue'
import { cmsService } from '@/services/cmsService'
import { siteSettingsService } from '@/services/siteSettingsService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import BaseInput from '@/components/form/BaseInput.vue'
import FormModal from '@/components/modal/FormModal.vue'
import { useSiteStatusStore } from '@/stores/siteStatus'
import { createTestGlobals } from '@/test-utils'
import { chooseNamedOption } from '@/test-utils/dropdown'

vi.mock('@/services/cmsService', () => ({
  cmsService: {
    getPage: vi.fn(),
    publishPage: vi.fn(),
    unpublishPage: vi.fn(),
    updatePage: vi.fn(),
    createSection: vi.fn(),
    updateSection: vi.fn(),
    toggleSection: vi.fn(),
    removeSection: vi.fn(),
    reorderSections: vi.fn(),
    createItem: vi.fn(),
    updateItem: vi.fn(),
    toggleItem: vi.fn(),
    removeItem: vi.fn(),
    reorderItems: vi.fn(),
    uploadImage: vi.fn(),
  },
}))
vi.mock('@/services/siteSettingsService', () => ({
  siteSettingsService: { get: vi.fn(), update: vi.fn() },
}))

function section(id, order, overrides = {}) {
  return {
    id,
    page: 'home',
    key: `section-${id}`,
    type: 'rich_text',
    enabled: true,
    order,
    title: { ar: `قسم ${id}`, en: `Section ${id}` },
    subtitle: null,
    description: null,
    ctaLabel: null,
    ctaUrl: null,
    icon: null,
    background: null,
    image: null,
    meta: null,
    items: [],
    updatedAt: '2026-11-01T08:00:00Z',
    ...overrides,
  }
}

function page(key = 'home', overrides = {}) {
  return {
    key,
    title: { ar: 'الصفحة', en: 'Page' },
    published: true,
    publishedAt: '2026-11-01T08:00:00Z',
    hasUnpublishedChanges: false,
    contentUpdatedAt: '2026-11-01T08:00:00Z',
    seo: { title: null, description: null },
    sections: [section(101, 1), section(102, 2), section(103, 3)],
    ...overrides,
  }
}

const SETTINGS = {
  social: [{ platform: 'x', url: 'https://x.com/moashir.example' }],
  contact: {
    email: 'info@moashir.example.sa',
    phone: null,
    address: { ar: 'الرياض', en: 'Riyadh' },
  },
  footerNote: { ar: 'حقوق', en: 'Rights' },
  quickLinks: [{ label: { ar: 'الرئيسية', en: 'Home' }, url: '/' }],
  seo: { title: null, description: null },
  featuredKpiLimit: 6,
  showFeaturedKpis: true,
  updatedAt: '2026-11-01T08:00:00Z',
}

async function mountView() {
  const globals = createTestGlobals({ locale: 'en' })
  const wrapper = mount(HomepageContent, { global: globals, attachTo: document.body })
  await flushPromises()
  return { wrapper, globals }
}

beforeEach(() => {
  vi.clearAllMocks()
  cmsService.getPage.mockImplementation((key) => Promise.resolve({ data: page(key) }))
  siteSettingsService.get.mockResolvedValue({ data: SETTINGS })
})

describe('Public site content editor (Content Officer)', () => {
  it('offers one tab per CMS page, the two legal texts and the site settings, homepage first', async () => {
    const { wrapper } = await mountView()
    expect(wrapper.findAll('.tabs__tab').map((tab) => tab.text())).toEqual([
      'Homepage',
      'Accredited assessment',
      'Advanced assessment',
      'Quick Excellence Kit',
      'Analytical lenses',
      'Indicators page',
      'Accessibility statement',
      'Terms of use',
      'Privacy policy',
      'Site settings',
    ])
    expect(cmsService.getPage).toHaveBeenCalledWith('home')
    expect(wrapper.findAll('.cms-section-row')).toHaveLength(3)
    expect(wrapper.find('.publication-control .badge').text()).toBe('Published')
    wrapper.unmount()
  })

  it('toggles a section and moves it down with the arrow buttons', async () => {
    cmsService.toggleSection.mockResolvedValue({ data: section(101, 1, { enabled: false }) })
    cmsService.reorderSections.mockResolvedValue({
      data: [section(102, 1), section(101, 2), section(103, 3)],
    })
    const { wrapper } = await mountView()
    await wrapper.find('.cms-section-row input[type="checkbox"]').setValue(false)
    await flushPromises()
    expect(cmsService.toggleSection).toHaveBeenCalledWith(101, false)
    await wrapper.findAll('.sortable-list__moves')[0].findAll('button')[1].trigger('click')
    await flushPromises()
    expect(cmsService.reorderSections).toHaveBeenCalledWith([102, 101, 103])
    expect(wrapper.find('.publication-control .badge').text()).toBe(
      'Published, with unpublished changes',
    )
    wrapper.unmount()
  })

  it('reorders the sections by drag and drop', async () => {
    cmsService.reorderSections.mockResolvedValue({
      data: [section(103, 1), section(101, 2), section(102, 3)],
    })
    const { wrapper } = await mountView()
    const rows = wrapper.findAll('.sortable-list__row')
    await rows[2].trigger('dragstart', { dataTransfer: { setData: vi.fn(), effectAllowed: '' } })
    await rows[0].trigger('dragover')
    await rows[0].trigger('drop')
    await flushPromises()
    expect(cmsService.reorderSections).toHaveBeenCalledWith([103, 101, 102])
    wrapper.unmount()
  })

  it('unpublishes the homepage only after confirmation and switches the public site to maintenance', async () => {
    cmsService.unpublishPage.mockResolvedValue({ data: page('home', { published: false }) })
    const { wrapper, globals } = await mountView()
    const unpublish = wrapper
      .findAll('.publication-control button')
      .find((button) => button.text().includes('Unpublish'))
    await unpublish.trigger('click')
    await flushPromises()
    expect(cmsService.unpublishPage).not.toHaveBeenCalled()
    useConfirm().accept()
    await flushPromises()
    expect(cmsService.unpublishPage).toHaveBeenCalledWith('home')
    expect(useSiteStatusStore(globals.pinia).maintenance).toBe(true)
    expect(wrapper.find('.publication-control .badge').text()).toBe('Not published')
    wrapper.unmount()
  })

  it('shows the server refusal when a page cannot be published', async () => {
    cmsService.getPage.mockResolvedValue({ data: page('home', { hasUnpublishedChanges: true }) })
    cmsService.publishPage.mockRejectedValue(
      Object.assign(new Error('empty'), {
        normalized: {
          code: 'cms.page_empty',
          message: 'The page has no enabled section to publish.',
        },
      }),
    )
    const { wrapper } = await mountView()
    await wrapper.find('.publication-control .btn--primary').trigger('click')
    useConfirm().accept()
    await flushPromises()
    expect(useToast().toasts.at(-1)).toMatchObject({
      type: 'error',
      message: 'The page has no enabled section to publish.',
    })
    wrapper.unmount()
  })

  it('creates a section with its key and type through the dialog', async () => {
    cmsService.createSection.mockResolvedValue({
      data: section(150, 4, { key: 'faq_block', type: 'faq' }),
    })
    const { wrapper } = await mountView()
    await wrapper.find('.cms-page-editor__toolbar .btn--primary').trigger('click')
    await flushPromises()
    const dialog = wrapper.findComponent(SectionFormDialog)
    const input = (name) =>
      dialog.findAllComponents(BaseInput).find((field) => field.props('name') === name)
    await input('key').find('input').setValue('faq_block')
    await chooseNamedOption(dialog, 'type', 'faq')
    await input('title.ar').find('input').setValue('الأسئلة الشائعة')
    dialog.findComponent(FormModal).vm.$emit('submit')
    await flushPromises()
    await vi.waitFor(() => expect(cmsService.createSection).toHaveBeenCalled())
    await flushPromises()
    expect(cmsService.createSection).toHaveBeenCalledWith(
      'home',
      expect.objectContaining({
        key: 'faq_block',
        type: 'faq',
        title: { ar: 'الأسئلة الشائعة', en: '' },
      }),
    )
    expect(wrapper.findAll('.cms-section-row')).toHaveLength(4)
    wrapper.unmount()
  })

  it('loads another page when its tab is selected', async () => {
    const { wrapper } = await mountView()
    await wrapper.findAll('.tabs__tab')[4].trigger('click')
    await flushPromises()
    expect(cmsService.getPage).toHaveBeenLastCalledWith('lenses')
    wrapper.unmount()
  })

  it('edits and saves the site settings with the featured indicator settings', async () => {
    siteSettingsService.update.mockImplementation((payload) =>
      Promise.resolve({ data: { ...SETTINGS, ...payload } }),
    )
    const { wrapper } = await mountView()
    await wrapper.findAll('.tabs__tab')[9].trigger('click')
    await flushPromises()
    await chooseNamedOption(wrapper, 'featuredKpiLimit', 4)
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    expect(siteSettingsService.update).toHaveBeenCalledWith(
      expect.objectContaining({ featuredKpiLimit: 4, showFeaturedKpis: true }),
    )
    wrapper.unmount()
  })

  it('maps the server field errors of the site settings to their fields', async () => {
    siteSettingsService.update.mockRejectedValue(
      Object.assign(new Error('invalid'), {
        normalized: {
          message: 'The submitted data is invalid.',
          fieldErrors: { 'social.0.url': ['The link must start with https://.'] },
        },
      }),
    )
    const { wrapper } = await mountView()
    await wrapper.findAll('.tabs__tab')[9].trigger('click')
    await flushPromises()
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('The link must start with https://.')
    wrapper.unmount()
  })
})
