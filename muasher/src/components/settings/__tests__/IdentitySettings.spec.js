import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import IdentityGeneralSettings from '@/components/settings/IdentityGeneralSettings.vue'
import IdentityMenusSettings from '@/components/settings/IdentityMenusSettings.vue'
import { siteSettingsService } from '@/services/siteSettingsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/siteSettingsService', () => ({
  siteSettingsService: { get: vi.fn(), update: vi.fn() },
}))

const SETTINGS = {
  platformName: { ar: 'منصة مؤشر', en: 'Moashir' },
  platformDescription: null,
  headerLinks: [
    { label: { ar: 'الرئيسية', en: 'Home' }, url: '/' },
    { label: { ar: 'مؤشرات الأداء', en: 'Performance Indicators' }, url: '/kpis' },
  ],
  social: [{ platform: 'x', url: 'https://x.com/moashir.example' }],
  contact: { email: 'info@moashir.example.sa', phone: null, address: { ar: 'الرياض', en: '' } },
  footerNote: { ar: 'حقوق', en: 'Rights' },
  quickLinks: [{ label: { ar: 'الرئيسية', en: 'Home' }, url: '/' }],
  seo: { title: null, description: null },
  featuredKpiLimit: 6,
  showFeaturedKpis: true,
}

async function mountForm(component) {
  const wrapper = mount(component, {
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  siteSettingsService.get.mockResolvedValue({ data: SETTINGS })
  siteSettingsService.update.mockImplementation((payload) =>
    Promise.resolve({ data: { ...SETTINGS, ...payload } }),
  )
})

describe('Brand identity — general tab', () => {
  it('edits the platform name and description and saves only the general fields', async () => {
    const wrapper = await mountForm(IdentityGeneralSettings)
    expect(wrapper.find('input[name="platformNameEn"]').element.value).toBe('Moashir')
    await wrapper.find('input[name="platformNameAr"]').setValue(' مؤشر ')
    await wrapper.find('textarea[name="platformDescriptionEn"]').setValue('Excellence')
    expect(wrapper.find('input[name="platformNameAr"]').attributes('required')).toBeDefined()
    expect(wrapper.text()).not.toContain("platform's default text")
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    const payload = siteSettingsService.update.mock.calls[0][0]
    expect(Object.keys(payload)).toEqual([
      'platformName',
      'platformDescription',
      'footerNote',
      'contact',
      'social',
      'seo',
    ])
    expect(payload.platformName).toEqual({ ar: 'مؤشر', en: 'Moashir' })
    expect(payload.platformDescription).toEqual({ ar: '', en: 'Excellence' })
    wrapper.unmount()
  })

  it('lists one fixed row per platform and sends only the filled links', async () => {
    const wrapper = await mountForm(IdentityGeneralSettings)
    const rows = wrapper.findAll('.social-links__row')
    expect(rows.map((row) => row.find('input').attributes('name'))).toEqual([
      'social-x',
      'social-linkedin',
      'social-youtube',
      'social-instagram',
      'social-facebook',
    ])
    expect(wrapper.find('input[name="social-x"]').element.value).toBe(
      'https://x.com/moashir.example',
    )
    expect(wrapper.find('input[name="social-youtube"]').attributes('placeholder')).toBe(
      'https://www.youtube.com/@account',
    )
    expect(wrapper.findAll('.social-links button')).toHaveLength(0)
    expect(wrapper.text()).toContain('Leave a link empty to hide that platform on the site.')
    await wrapper
      .find('input[name="social-youtube"]')
      .setValue(' https://www.youtube.com/@moashir ')
    await wrapper.find('input[name="social-x"]').setValue('')
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    expect(siteSettingsService.update.mock.calls[0][0].social).toEqual([
      { platform: 'youtube', url: 'https://www.youtube.com/@moashir' },
    ])
    wrapper.unmount()
  })

  it('maps the server field errors to their fields', async () => {
    siteSettingsService.update.mockRejectedValue(
      Object.assign(new Error('invalid'), {
        normalized: {
          message: 'The submitted data is invalid.',
          fieldErrors: { 'social.0.url': ['The link must start with https://.'] },
        },
      }),
    )
    const wrapper = await mountForm(IdentityGeneralSettings)
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('The link must start with https://.')
    expect(wrapper.find('input[name="social-x"]').attributes('aria-invalid')).toBe('true')
    wrapper.unmount()
  })
})

describe('Brand identity — menus tab', () => {
  function treeButtons(wrapper, index) {
    return wrapper.findAll('fieldset')[index].findAll('.menu-tree__select')
  }

  it('lists the links as a tree and edits the selected one in the side panel', async () => {
    const wrapper = await mountForm(IdentityMenusSettings)
    const header = treeButtons(wrapper, 0)
    expect(header.map((button) => button.text())).toEqual(['Home', 'Performance Indicators'])
    expect(header[0].attributes('aria-current')).toBe('true')
    expect(wrapper.find('input[name="headerLinks-0-label-en"]').element.value).toBe('Home')
    await header[1].trigger('click')
    expect(header[1].attributes('aria-current')).toBe('true')
    expect(wrapper.find('input[name="headerLinks-1-label-en"]').element.value).toBe(
      'Performance Indicators',
    )
    expect(wrapper.findAll('fieldset')[0].find('.menu-panel').text()).toContain(
      'Performance indicators page',
    )
    expect(treeButtons(wrapper, 1)[0].text()).toBe('Home')
    wrapper.unmount()
  })

  it('hides a link, adds a dropdown sub-link and saves both menus only', async () => {
    const wrapper = await mountForm(IdentityMenusSettings)
    const header = wrapper.findAll('fieldset')[0]
    await header.find('input[name="headerLinks-0-visible"]').setValue(false)
    expect(header.find('.menu-tree__item').classes()).toContain('is-hidden')
    expect(header.find('.menu-tree__item').text()).toContain('Hidden')
    await header
      .find('button[aria-label="Add a sub-link under Performance Indicators"]')
      .trigger('click')
    await header.find('input[name="headerLinks-1-children-0-label-ar"]').setValue('الشروط')
    await header.find('input[name="headerLinks-1-children-0-label-en"]').setValue('Terms')
    expect(header.findAll('.menu-tree__item')[1].text()).toContain('Dropdown (1)')
    await header.findAll('.menu-tree__select')[1].trigger('click')
    expect(header.find('.menu-panel__note').text()).toContain('opens a dropdown with 1 sub-links')
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    expect(siteSettingsService.update).toHaveBeenCalledWith({
      headerLinks: [
        { label: { ar: 'الرئيسية', en: 'Home' }, url: '/', isVisible: false, children: [] },
        {
          label: { ar: 'مؤشرات الأداء', en: 'Performance Indicators' },
          url: null,
          isVisible: true,
          children: [{ label: { ar: 'الشروط', en: 'Terms' }, url: '/', isVisible: true }],
        },
      ],
      quickLinks: [{ label: { ar: 'الرئيسية', en: 'Home' }, url: '/', isVisible: true }],
    })
    wrapper.unmount()
  })

  it('adds a custom link, reorders and removes links', async () => {
    siteSettingsService.get.mockResolvedValue({
      data: {
        ...SETTINGS,
        quickLinks: [{ label: { ar: 'موقع', en: 'Site' }, url: 'https://moashir.example.sa' }],
      },
    })
    const wrapper = await mountForm(IdentityMenusSettings)
    const footer = wrapper.findAll('fieldset')[1]
    expect(footer.find('input[name="quickLinks-0-url"]').element.value).toBe(
      'https://moashir.example.sa',
    )
    const header = wrapper.findAll('fieldset')[0]
    const moveUp = header
      .findAll('button')
      .find((button) => button.attributes('aria-label') === 'Move “Performance Indicators” up')
    await moveUp.trigger('click')
    expect(treeButtons(wrapper, 0).map((button) => button.text())).toEqual([
      'Performance Indicators',
      'Home',
    ])
    await header.findAll('.menu-tree__select')[0].trigger('click')
    await header.findAll('.menu-panel__foot button')[0].trigger('click')
    expect(treeButtons(wrapper, 0).map((button) => button.text())).toEqual(['Home'])
    const add = header.findAll('button').find((button) => button.text() === 'Add header link')
    await add.trigger('click')
    expect(treeButtons(wrapper, 0)).toHaveLength(2)
    expect(treeButtons(wrapper, 0)[1].attributes('aria-current')).toBe('true')
    wrapper.unmount()
  })

  it('says the header menu is empty once every link is removed', async () => {
    siteSettingsService.get.mockResolvedValue({ data: { ...SETTINGS, headerLinks: [] } })
    const wrapper = await mountForm(IdentityMenusSettings)
    expect(wrapper.text()).toContain('The header menu is empty; no links will be shown.')
    wrapper.unmount()
  })

  it('shows a refused header link under its field', async () => {
    siteSettingsService.get.mockResolvedValue({
      data: { ...SETTINGS, headerLinks: [{ label: { ar: 'رابط', en: 'Link' }, url: 'x' }] },
    })
    siteSettingsService.update.mockRejectedValue(
      Object.assign(new Error('invalid'), {
        normalized: { fieldErrors: { 'headerLinks.0.url': ['The link is not safe.'] } },
      }),
    )
    const wrapper = await mountForm(IdentityMenusSettings)
    await wrapper.find('.site-settings__form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('The link is not safe.')
    expect(wrapper.find('.menu-tree__item').classes()).toContain('is-invalid')
    wrapper.unmount()
  })
})
