import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import HomepageContent from '@/views/settings/HomepageContent.vue'
import FormModal from '@/components/modal/FormModal.vue'
import HomepageItemsEditor from '@/components/settings/HomepageItemsEditor.vue'
import SiteSettingsEditor from '@/components/settings/SiteSettingsEditor.vue'
import { useConfirm } from '@/composables/useConfirm'
import { createTestGlobals } from '@/test-utils'

const toggleSection = vi.fn()
const reorderSections = vi.fn()
const updateSection = vi.fn()
const upsertItem = vi.fn()
const removeItem = vi.fn()
const reorderItems = vi.fn()
const loadSiteSettings = vi.fn()
const updateSiteSettings = vi.fn()
const load = vi.fn()
const setPublished = vi.fn()

const siteSettings = {
  footerNote: { ar: 'حقوق', en: 'Rights' },
  contact: { email: 'info@moashir.example.sa', phone: '+966', address: { ar: 'الرياض', en: 'Riyadh' } },
  social: [{ platform: 'x', url: 'https://x.com/muasher' }],
  quickLinks: [{ label: { ar: 'الرئيسية', en: 'Home' }, url: '/' }],
}

const sections = [
  {
    id: 'sec-hero',
    key: 'hero',
    enabled: true,
    order: 1,
    title: { ar: 'واجهة', en: 'Hero' },
    subtitle: { ar: '', en: '' },
    description: { ar: '', en: '' },
    ctaLabel: { ar: '', en: '' },
    ctaUrl: '',
    image: '',
    icon: '',
    background: '',
    items: [],
  },
  {
    id: 'sec-paths',
    key: 'evaluation-paths',
    enabled: false,
    order: 2,
    title: { ar: 'المسارات', en: 'Paths' },
    subtitle: { ar: '', en: '' },
    description: { ar: '', en: '' },
    ctaLabel: { ar: '', en: '' },
    ctaUrl: '',
    image: '',
    icon: '',
    background: '',
    items: [
      {
        id: 'path-self',
        order: 1,
        icon: 'fa-solid fa-list-check',
        image: '',
        title: { ar: 'الذاتي', en: 'Self' },
        description: { ar: '', en: '' },
        ctaLabel: { ar: '', en: '' },
        ctaUrl: '',
        meta: {},
      },
      {
        id: 'path-accredited',
        order: 2,
        icon: 'fa-solid fa-clipboard-check',
        image: '',
        title: { ar: 'المعتمد', en: 'Accredited' },
        description: { ar: '', en: '' },
        ctaLabel: { ar: '', en: '' },
        ctaUrl: '',
        meta: {},
      },
    ],
  },
]

vi.mock('@/composables/useHomeContent', () => ({
  useHomeAdmin: () => ({
    orderedSections: ref(sections),
    siteSettings: ref(siteSettings),
    published: ref(true),
    setPublished,
    status: ref('success'),
    saving: ref(false),
    localized: (value) => value?.en ?? '',
    load,
    updateSection,
    toggleSection,
    reorderSections,
    upsertItem,
    removeItem,
    reorderItems,
    loadSiteSettings,
    updateSiteSettings,
  }),
}))

function mountView() {
  return mount(HomepageContent, {
    attachTo: document.body,
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('HomepageContent (admin homepage CMS)', () => {
  beforeEach(() => {
    toggleSection.mockReset().mockResolvedValue({})
    reorderSections.mockReset().mockResolvedValue({})
    updateSection.mockReset().mockResolvedValue({})
    upsertItem.mockReset().mockResolvedValue({})
    removeItem.mockReset().mockResolvedValue({})
    reorderItems.mockReset().mockResolvedValue({})
    loadSiteSettings.mockReset().mockResolvedValue(siteSettings)
    updateSiteSettings.mockReset().mockResolvedValue(siteSettings)
    setPublished.mockReset().mockResolvedValue(false)
  })

  it('shows the publication state and unpublishes only after confirmation', async () => {
    const wrapper = mountView()
    expect(wrapper.find('.publication-control').text()).toContain('Published')
    await wrapper.find('.publication-control .btn').trigger('click')
    await flushPromises()
    expect(setPublished).not.toHaveBeenCalled()
    useConfirm().accept()
    await flushPromises()
    expect(setPublished).toHaveBeenCalledWith(false)
    wrapper.unmount()
  })

  it('lists all sections in order with their key and status', () => {
    const wrapper = mountView()
    const text = wrapper.text()
    expect(text).toContain('Hero')
    expect(text).toContain('hero')
    expect(text).toContain('Paths')
    expect(text).toContain('evaluation-paths')
  })

  it('toggles a section via toggleSection', async () => {
    const wrapper = mountView()
    const firstSwitch = wrapper.find('input[role="switch"]')
    await firstSwitch.setValue(false)
    await flushPromises()
    expect(toggleSection).toHaveBeenCalledWith('sec-hero', false)
  })

  it('reorders sections via reorderSections when moving a row down', async () => {
    const wrapper = mountView()
    const downButton = wrapper.find('button[aria-label="Move down"]')
    await downButton.trigger('click')
    await flushPromises()
    expect(reorderSections).toHaveBeenCalledWith(['sec-paths', 'sec-hero'])
  })

  it('opens the edit modal pre-filled with the section fields', async () => {
    const wrapper = mountView()
    const editButton = wrapper.findAll('button').find((btn) => btn.text().includes('Edit'))
    await editButton.trigger('click')
    await flushPromises()
    const modal = wrapper.getComponent(FormModal)
    expect(modal.props('modelValue')).toBe(true)
    const titleAr = document.querySelector('input[name="titleAr"]')
    const titleEn = document.querySelector('input[name="titleEn"]')
    expect(titleAr).not.toBeNull()
    expect(titleAr.value).toBe('واجهة')
    expect(titleEn.value).toBe('Hero')
    wrapper.unmount()
  })

  it('shows a manage-items action for item-bearing sections only', () => {
    const wrapper = mountView()
    const manageButtons = wrapper.findAll('button').filter((btn) => btn.text().includes('Manage items'))
    expect(manageButtons).toHaveLength(2)
  })

  it('opens the items editor for the selected section and forwards upsert', async () => {
    const wrapper = mountView()
    const manageButtons = wrapper.findAll('button').filter((btn) => btn.text().includes('Manage items'))
    await manageButtons[1].trigger('click')
    await flushPromises()

    const editor = wrapper.getComponent(HomepageItemsEditor)
    expect(editor.props('modelValue')).toBe(true)
    expect(editor.props('section').id).toBe('sec-paths')
    expect(editor.props('items')).toHaveLength(2)

    editor.vm.$emit('upsert', { title: { ar: 'جديد', en: 'New' } })
    await flushPromises()
    expect(upsertItem).toHaveBeenCalledWith('sec-paths', { title: { ar: 'جديد', en: 'New' } })

    editor.vm.$emit('reorder', ['path-accredited', 'path-self'])
    await flushPromises()
    expect(reorderItems).toHaveBeenCalledWith('sec-paths', ['path-accredited', 'path-self'])

    editor.vm.$emit('remove', 'path-self')
    await flushPromises()
    expect(removeItem).toHaveBeenCalledWith('sec-paths', 'path-self')
    wrapper.unmount()
  })

  it('removing an item asks for confirmation then forwards removeItem', async () => {
    const wrapper = mountView()
    const manageButtons = wrapper.findAll('button').filter((btn) => btn.text().includes('Manage items'))
    await manageButtons[1].trigger('click')
    await flushPromises()

    const removeButton = document.querySelector('button[aria-label="Remove"]')
    expect(removeButton).not.toBeNull()
    removeButton.click()
    await flushPromises()

    const { confirmState, accept } = useConfirm()
    expect(confirmState.isOpen).toBe(true)
    accept()
    await flushPromises()
    expect(removeItem).toHaveBeenCalledWith('sec-paths', 'path-self')
    wrapper.unmount()
  })

  it('loads site settings on mount and saves via updateSiteSettings', async () => {
    const wrapper = mountView()
    await flushPromises()
    expect(loadSiteSettings).toHaveBeenCalled()

    const editor = wrapper.getComponent(SiteSettingsEditor)
    editor.vm.$emit('save', { footerNote: { ar: 'محدث', en: 'Updated' } })
    await flushPromises()
    expect(updateSiteSettings).toHaveBeenCalledWith({ footerNote: { ar: 'محدث', en: 'Updated' } })
    wrapper.unmount()
  })
})
