import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BrandingEditor from '@/components/branding/BrandingEditor.vue'
import { brandingService } from '@/services/brandingService'
import { useBrandingStore } from '@/stores/branding'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { deriveBrandScheme } from '@/utils/brandingContrast'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/brandingService', () => ({
  brandingService: { get: vi.fn(), update: vi.fn(), reset: vi.fn(), uploadLogo: vi.fn() },
}))

const DEFAULTS = { primary: '#98d8d3', secondary: '#0a2325' }

function theme(colors = DEFAULTS, logos = { dark: null }, overrides = {}) {
  return {
    data: {
      colors,
      overrides,
      scheme: deriveBrandScheme(colors, overrides),
      defaultColors: DEFAULTS,
      logos,
      updatedAt: null,
    },
  }
}

async function typeColor(wrapper, role, value) {
  await wrapper.find(`.brand-color-field[data-role="${role}"] input[type="text"]`).setValue(value)
}

async function mountView(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(BrandingEditor, { global: globals, attachTo: document.body })
  await flushPromises()
  return { wrapper, globals }
}

beforeEach(() => {
  vi.clearAllMocks()
  brandingService.get.mockResolvedValue(theme())
  brandingService.update.mockImplementation(({ primaryColor, secondaryColor }) =>
    Promise.resolve(theme({ primary: primaryColor, secondary: secondaryColor })),
  )
  brandingService.reset.mockResolvedValue(theme())
  brandingService.uploadLogo.mockResolvedValue({ data: { fileId: 1, url: 'blob:x' } })
  globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview')
  globalThis.URL.revokeObjectURL = vi.fn()
})

describe('Visual identity editor (System Administrator)', () => {
  it('offers a primary and a secondary colour field under «Base brand colors», with no lock notice', async () => {
    const { wrapper } = await mountView('en')
    const fields = wrapper.findAll('.brand-color-field')
    expect(fields.map((field) => field.attributes('data-role'))).toEqual(['primary', 'secondary'])
    expect(fields[0].find('input[type="text"]').element.value).toBe('#98d8d3')
    expect(fields[1].find('input[type="text"]').element.value).toBe('#0a2325')
    expect(wrapper.text()).toContain('Base brand colors')
    expect(wrapper.find('.branding-page__lock').exists()).toBe(false)
    wrapper.unmount()
  })

  it('keeps a chosen colour as a draft until it is saved', async () => {
    const { wrapper } = await mountView()
    await typeColor(wrapper, 'primary', '#d4088c')
    expect(useBrandingStore().scheme.primary).toBe('#98d8d3')
    wrapper.unmount()
  })

  it('keeps save disabled until something changes, then saves both colours and applies the theme', async () => {
    const { wrapper } = await mountView()
    const save = wrapper.find('.branding-page__actions .btn--primary')
    expect(save.attributes('disabled')).toBeDefined()
    await typeColor(wrapper, 'primary', '#D4088C')
    await typeColor(wrapper, 'secondary', '#4a2a7d')
    expect(save.attributes('disabled')).toBeUndefined()
    await save.trigger('click')
    await flushPromises()
    expect(brandingService.update).toHaveBeenCalledWith({
      primaryColor: '#d4088c',
      secondaryColor: '#4a2a7d',
      colorOverrides: {},
      reason: null,
    })
    expect(useBrandingStore().scheme).toMatchObject({ primary: '#d4088c', secondary: '#4a2a7d' })
    wrapper.unmount()
  })

  it('blocks saving only while a colour code is invalid', async () => {
    const { wrapper } = await mountView('en')
    const save = wrapper.find('.branding-page__actions .btn--primary')
    await typeColor(wrapper, 'primary', '#12')
    expect(save.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Enter the color as a six-digit code')
    await typeColor(wrapper, 'primary', '#777777')
    expect(save.attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('runs no contrast check: a low-contrast colour shows no banner and can be saved', async () => {
    const { wrapper } = await mountView('ar')
    await typeColor(wrapper, 'primary', '#777777')
    await typeColor(wrapper, 'secondary', '#777777')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('التباين')
    expect(wrapper.text()).not.toContain('4.5:1')
    expect(wrapper.text()).toContain('تقع مسؤولية وضوح الألوان المخصصة على الجهة المالكة للمنصة')
    const save = wrapper.find('.branding-page__actions .btn--primary')
    expect(save.attributes('disabled')).toBeUndefined()
    await save.trigger('click')
    await flushPromises()
    expect(brandingService.update).toHaveBeenCalledWith(
      expect.objectContaining({ primaryColor: '#777777', secondaryColor: '#777777' }),
    )
    wrapper.unmount()
  })

  it('lists the five derived colours on auto, and overrides one as a draft', async () => {
    const { wrapper } = await mountView('en')
    const rows = wrapper.findAll('.brand-derived__row')
    expect(rows).toHaveLength(5)
    expect(rows.every((row) => row.text().includes('Auto'))).toBe(true)
    const hover = rows[2]
    expect(hover.text()).toContain('Hover & pressed')
    expect(hover.text()).toContain('#88c0bc')
    await hover.find('.btn').trigger('click')
    expect(wrapper.findAll('.brand-derived__row')[2].text()).toContain('Custom')
    await wrapper.find('.brand-derived__picker').setValue('#7cc5bf')
    const custom = wrapper.findAll('.brand-derived__row')[2]
    expect(custom.text()).toContain('#7cc5bf')
    expect(custom.text()).toContain('Auto: #88c0bc')
    expect(useBrandingStore().scheme.primaryHover).toBe('#88c0bc')
    wrapper.unmount()
  })

  it('saves the overrides with the colours and sets a colour back to auto', async () => {
    brandingService.update.mockImplementation(({ primaryColor, secondaryColor, colorOverrides }) =>
      Promise.resolve(
        theme({ primary: primaryColor, secondary: secondaryColor }, { dark: null }, colorOverrides),
      ),
    )
    const { wrapper } = await mountView('en')
    await wrapper.findAll('.brand-derived__row')[3].find('.btn').trigger('click')
    await wrapper.find('.brand-derived__picker').setValue('#e6f4f3')
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    expect(brandingService.update).toHaveBeenCalledWith({
      primaryColor: '#98d8d3',
      secondaryColor: '#0a2325',
      colorOverrides: { primarySoft: '#e6f4f3' },
      reason: null,
    })
    expect(useBrandingStore().scheme.primarySoft).toBe('#e6f4f3')
    const row = wrapper.findAll('.brand-derived__row')[3]
    expect(row.text()).toContain('Custom')
    await row.find('.btn').trigger('click')
    expect(wrapper.findAll('.brand-derived__row')[3].text()).toContain('Auto')
    expect(
      wrapper.find('.branding-page__actions .btn--primary').attributes('disabled'),
    ).toBeUndefined()
    wrapper.unmount()
  })

  it('saves a low-contrast override exactly as chosen, with no warning', async () => {
    brandingService.update.mockImplementation(({ primaryColor, secondaryColor, colorOverrides }) =>
      Promise.resolve(
        theme({ primary: primaryColor, secondary: secondaryColor }, { dark: null }, colorOverrides),
      ),
    )
    const { wrapper } = await mountView('en')
    await wrapper.findAll('.brand-derived__row')[4].find('.btn').trigger('click')
    await wrapper.find('.brand-derived__picker').setValue('#6b8a8c')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.find('.brand-derived').text()).not.toMatch(/contrast|:1/i)
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    expect(brandingService.update).toHaveBeenCalledWith(
      expect.objectContaining({ colorOverrides: { onSecondary: '#6b8a8c' } }),
    )
    expect(useBrandingStore().scheme.onSecondary).toBe('#6b8a8c')
    wrapper.unmount()
  })

  it('gives no colour advice, even for a primary close to the error colour', async () => {
    const { wrapper } = await mountView('ar')
    await typeColor(wrapper, 'primary', '#d63031')
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('«خطأ»')
    expect(
      wrapper.find('.branding-page__actions .btn--primary').attributes('disabled'),
    ).toBeUndefined()
    wrapper.unmount()
  })

  it('shows the fixed colours read only and previews the back office and the portal', async () => {
    const { wrapper } = await mountView('ar')
    expect(wrapper.find('.brand-fixed').text()).toContain('للعرض فقط')
    expect(wrapper.findAll('.brand-fixed__item')).toHaveLength(11)
    expect(wrapper.findAll('.brand-fixed input, .brand-fixed button')).toHaveLength(0)
    const tabs = wrapper.findAll('.brand-preview [role="tab"]')
    expect(tabs.map((tab) => tab.text())).toEqual(['لوحة التحكم', 'بوابة الجهة'])
    expect(wrapper.find('.brand-preview__stage').attributes('aria-label')).toContain('لوحة التحكم')
    await tabs[1].trigger('click')
    expect(wrapper.find('.brand-sample--portal').exists()).toBe(true)
    wrapper.unmount()
  })

  it('fills the Moashir default colours back into the draft', async () => {
    brandingService.get.mockResolvedValue(theme({ primary: '#d4088c', secondary: '#4a2a7d' }))
    const { wrapper } = await mountView('en')
    const restore = wrapper.find('.brand-colors__default')
    expect(restore.text()).toBe('Use the Moashir default colors')
    await restore.trigger('click')
    expect(
      wrapper.find('.brand-color-field[data-role="primary"] input[type="text"]').element.value,
    ).toBe('#98d8d3')
    expect(wrapper.find('.brand-colors__default').exists()).toBe(false)
    wrapper.unmount()
  })

  it('offers the back-office logo, the favicon and the public site logo with their exact specs', async () => {
    const { wrapper } = await mountView('en')
    const fields = wrapper.findAll('.logo-variant')
    expect(fields.map((field) => field.attributes('data-surface'))).toEqual([
      'dark',
      'favicon',
      'site',
    ])
    expect(fields[0].text()).toContain('Back-office logo')
    expect(fields[0].text()).toContain(
      'PNG with a transparent background — \u2066432×144\u2069 px — up to 1 MB',
    )
    expect(fields[1].text()).toContain('Tab icon (favicon)')
    expect(fields[1].text()).toContain('512×512\u2069 px')
    expect(fields[2].text()).toContain('Public site logo')
    expect(fields[2].text()).toContain('480×160\u2069 px')
    expect(wrapper.find('.logo-variant .badge').exists()).toBe(false)
    expect(wrapper.find('.logo-variant__image').attributes('src')).toMatch(/moashir-logo-on-dark/)
    wrapper.unmount()
  })

  it('accepts a logo whose size differs from the spec and says so', async () => {
    const OriginalImage = globalThis.Image
    globalThis.Image = class {
      set src(_) {
        this.naturalWidth = 300
        this.naturalHeight = 200
        queueMicrotask(() => this.onload())
      }
    }
    const { wrapper } = await mountView('en')
    const uploads = wrapper.findAllComponents({ name: 'BaseFileUpload' })
    uploads[1].vm.$emit('select', [new File(['png'], 'icon.png', { type: 'image/png' })])
    await flushPromises()
    const favicon = wrapper.findAll('.logo-variant')[1]
    expect(favicon.find('.logo-variant__warning').text()).toBe(
      'The image is \u2066300×200\u2069 px, not the expected \u2066512×512\u2069. You can still save it.',
    )
    expect(favicon.find('.field__error').exists()).toBe(false)
    expect(
      wrapper.find('.branding-page__actions .btn--primary').attributes('disabled'),
    ).toBeUndefined()
    globalThis.Image = OriginalImage
    wrapper.unmount()
  })

  it('shows the saved dark-background logo in its field and ignores a stored light one', async () => {
    brandingService.get.mockResolvedValue(
      theme(DEFAULTS, { dark: 'blob:on-dark', light: 'blob:on-light' }),
    )
    const { wrapper } = await mountView('en')
    expect(wrapper.find('.logo-variant__image').attributes('src')).toBe('blob:on-dark')
    expect(wrapper.html()).not.toContain('blob:on-light')
    wrapper.unmount()
  })

  it('offers no per-logo restore; only the global reset returns the logos to the brand defaults', async () => {
    brandingService.get.mockResolvedValue(
      theme(
        { primary: '#a63d1f', secondary: '#4a2a7d' },
        { dark: 'blob:on-dark', light: 'blob:on-light' },
      ),
    )
    const { wrapper } = await mountView()
    wrapper.findAll('.logo-variant').forEach((field) => {
      expect(field.findAll('button').map((button) => button.classes())).toEqual([
        ['file-upload__dropzone'],
      ])
    })
    expect(wrapper.findAll('.branding-page__actions .btn')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('استعادة الشعار الافتراضي')
    wrapper.unmount()
  })

  it('validates the logo type before any upload', async () => {
    const { wrapper } = await mountView('en')
    const upload = wrapper.findComponent({ name: 'BaseFileUpload' })
    upload.vm.$emit('select', [new File(['x'], 'logo.gif', { type: 'image/gif' })])
    await flushPromises()
    expect(wrapper.text()).toContain('File type not allowed')
    expect(brandingService.uploadLogo).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('uploads the selected logo with its dark-background surface on save', async () => {
    const { wrapper } = await mountView()
    const uploads = wrapper.findAllComponents({ name: 'BaseFileUpload' })
    expect(uploads).toHaveLength(3)
    uploads[0].vm.$emit('select', [new File(['png'], 'logo-light.png', { type: 'image/png' })])
    await flushPromises()
    expect(wrapper.find('.logo-variant__image').attributes('src')).toBe('blob:preview')
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    expect(brandingService.uploadLogo).toHaveBeenCalledTimes(1)
    expect(brandingService.uploadLogo).toHaveBeenCalledWith(expect.any(File), 'dark', null)
    expect(brandingService.update).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('resets the colours and the logos to the brand defaults only after confirmation', async () => {
    brandingService.get.mockResolvedValue(
      theme(
        { primary: '#a63d1f', secondary: '#4a2a7d' },
        { dark: 'blob:on-dark', light: 'blob:on-light' },
      ),
    )
    const { wrapper } = await mountView('en')
    await wrapper.find('.branding-page__actions .btn--ghost').trigger('click')
    await flushPromises()
    expect(brandingService.reset).not.toHaveBeenCalled()
    useConfirm().accept()
    await flushPromises()
    expect(brandingService.reset).toHaveBeenCalledTimes(1)
    expect(useBrandingStore().scheme.primary).toBe('#98d8d3')
    expect(useBrandingStore().logos).toEqual({ dark: null, favicon: null, site: null })
    wrapper.unmount()
  })

  it('keeps the saved identity when the global reset fails', async () => {
    brandingService.get.mockResolvedValue(
      theme({ primary: '#a63d1f', secondary: '#4a2a7d' }, { dark: 'blob:on-dark' }),
    )
    brandingService.reset.mockRejectedValueOnce(new Error('offline'))
    const { wrapper } = await mountView('en')
    await wrapper.find('.branding-page__actions .btn--ghost').trigger('click')
    await flushPromises()
    useConfirm().accept()
    await flushPromises()
    expect(useBrandingStore().scheme.primary).toBe('#a63d1f')
    expect(wrapper.findAll('.logo-variant__image')[0].attributes('src')).toBe('blob:on-dark')
    expect(useToast().toasts.at(-1)).toMatchObject({
      type: 'error',
      message: 'Could not save the identity. Please try again.',
    })
    wrapper.unmount()
  })

  it('keeps the saved colours and shows the server message when a save fails', async () => {
    brandingService.update.mockRejectedValueOnce(
      Object.assign(new Error('validation'), {
        normalized: {
          code: 'validation_failed',
          message: 'The given data was invalid.',
          fieldErrors: {},
        },
      }),
    )
    const { wrapper } = await mountView('en')
    await typeColor(wrapper, 'secondary', '#4a2a7d')
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    expect(useBrandingStore().scheme.secondary).toBe('#0a2325')
    expect(useToast().toasts.at(-1)).toMatchObject({
      type: 'error',
      message: 'The given data was invalid.',
    })
    wrapper.unmount()
  })

  it('asks the Super Admin for a reason and sends it with every change', async () => {
    const { wrapper, globals } = await mountView('en')
    const { useAuthStore } = await import('@/stores/auth')
    useAuthStore(globals.pinia).user = {
      id: 1,
      name: 'مدير النظام الرئيسي',
      roles: [],
      permissions: [],
      isSuperAdmin: true,
    }
    await typeColor(wrapper, 'primary', '#0b7068')
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    expect(brandingService.update).not.toHaveBeenCalled()
    const dialog = document.body.querySelector('.reason-dialog textarea')
    expect(dialog).not.toBeNull()
    wrapper.unmount()
  })

  it('shows the error state with retry when the theme cannot be loaded', async () => {
    brandingService.get.mockRejectedValueOnce(new Error('offline'))
    const { wrapper } = await mountView('en')
    expect(wrapper.find('.error-state').exists()).toBe(true)
    await wrapper.find('.error-state__retry .btn').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.brand-color-field')).toHaveLength(2)
    wrapper.unmount()
  })
})
