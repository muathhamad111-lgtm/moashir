import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Branding from '@/views/settings/Branding.vue'
import { brandingService } from '@/services/brandingService'
import { useBrandingStore } from '@/stores/branding'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { brandingPaletteFixture } from '@/mocks/fixtures/branding'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/brandingService', () => ({
  brandingService: { get: vi.fn(), update: vi.fn(), reset: vi.fn(), uploadLogo: vi.fn() },
}))

const palette = brandingPaletteFixture.map((entry) => ({
  token: entry.token,
  hex: entry.hex,
  textOnLightHex: entry.textOnLightHex,
  label: entry.label,
}))

function theme(accentToken = 'brand_default', logos = { dark: null }) {
  return { data: { accentToken, logos, palette, updatedAt: null } }
}

async function mountView(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(Branding, { global: globals, attachTo: document.body })
  await flushPromises()
  return { wrapper, globals }
}

beforeEach(() => {
  vi.clearAllMocks()
  brandingService.get.mockResolvedValue(theme())
  brandingService.update.mockImplementation(({ accentToken }) =>
    Promise.resolve(theme(accentToken)),
  )
  brandingService.reset.mockResolvedValue(theme())
  brandingService.uploadLogo.mockResolvedValue({ data: { fileId: 1, url: 'blob:x' } })
  globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview')
  globalThis.URL.revokeObjectURL = vi.fn()
})

describe('Branding settings (System Administrator)', () => {
  it('lists the 9 palette options with bilingual labels and the lock notice', async () => {
    const { wrapper } = await mountView('en')
    expect(wrapper.findAll('.accent-swatch')).toHaveLength(9)
    expect(wrapper.text()).toContain('Brand default')
    expect(wrapper.find('.branding-page__lock').text()).toContain('System Administrator')
    wrapper.unmount()
  })

  it('previews the selected accent in dark and light frames, both with the dark sidebar logo', async () => {
    const { wrapper } = await mountView()
    await wrapper.findAll('.accent-swatch__input')[6].setValue(true)
    const frames = wrapper.findAll('.branding-preview__frame')
    expect(frames.map((frame) => frame.attributes('data-theme'))).toEqual(['dark', 'light'])
    frames.forEach((frame) => expect(frame.attributes('data-accent')).toBe('amber'))
    const logos = frames.map((frame) => frame.find('.branding-preview__logo').attributes('src'))
    expect(logos[0]).toMatch(/moashir-logo-on-dark/)
    expect(logos[1]).toBe(logos[0])
    expect(useBrandingStore().accentToken).toBe('brand_default')
    wrapper.unmount()
  })

  it('shows a passing AA contrast check for the selected color', async () => {
    const { wrapper } = await mountView('en')
    const rows = wrapper.findAll('.contrast-check__row')
    expect(rows).toHaveLength(4)
    rows.forEach((row) => expect(row.text()).toContain('Passes AA'))
    wrapper.unmount()
  })

  it('keeps save disabled until something changes, then saves and applies the theme', async () => {
    const { wrapper } = await mountView()
    const save = wrapper.find('.branding-page__actions .btn--primary')
    expect(save.attributes('disabled')).toBeDefined()
    await wrapper.findAll('.accent-swatch__input')[2].setValue(true)
    expect(save.attributes('disabled')).toBeUndefined()
    await save.trigger('click')
    await flushPromises()
    expect(brandingService.update).toHaveBeenCalledWith({ accentToken: 'teal', reason: null })
    expect(useBrandingStore().accentToken).toBe('teal')
    wrapper.unmount()
  })

  it('offers only the dark-background logo used by the always-dark sidebar, on the brand default', async () => {
    const { wrapper } = await mountView('en')
    const fields = wrapper.findAll('.logo-variant')
    expect(fields.map((field) => field.attributes('data-surface'))).toEqual(['dark'])
    expect(fields[0].text()).toContain('Logo for dark backgrounds')
    expect(fields[0].text()).toContain('Shown in the sidebar in both light and dark modes')
    expect(fields[0].text()).toContain('Brand default')
    expect(wrapper.text()).not.toContain('Logo for light backgrounds')
    expect(wrapper.find('.logo-variant__image').attributes('src')).toMatch(/moashir-logo-on-dark/)
    wrapper.unmount()
  })

  it('shows the saved dark-background logo in both preview frames and ignores a stored light one', async () => {
    brandingService.get.mockResolvedValue(
      theme('brand_default', { dark: 'blob:on-dark', light: 'blob:on-light' }),
    )
    const { wrapper } = await mountView('en')
    const frames = wrapper.findAll('.branding-preview__frame')
    frames.forEach((frame) =>
      expect(frame.find('.branding-preview__logo').attributes('src')).toBe('blob:on-dark'),
    )
    expect(wrapper.html()).not.toContain('blob:on-light')
    expect(wrapper.find('.logo-variant').text()).toContain('Custom logo')
    wrapper.unmount()
  })

  it('offers no per-logo restore; only the global reset returns the logos to the brand defaults', async () => {
    brandingService.get.mockResolvedValue(
      theme('coral', { dark: 'blob:on-dark', light: 'blob:on-light' }),
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
    expect(uploads).toHaveLength(1)
    uploads[0].vm.$emit('select', [new File(['png'], 'logo-light.png', { type: 'image/png' })])
    await flushPromises()
    expect(wrapper.find('.logo-variant__image').attributes('src')).toBe('blob:preview')
    wrapper
      .findAll('.branding-preview__logo')
      .forEach((logo) => expect(logo.attributes('src')).toBe('blob:preview'))
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    expect(brandingService.uploadLogo).toHaveBeenCalledTimes(1)
    expect(brandingService.uploadLogo).toHaveBeenCalledWith(expect.any(File), 'dark', null)
    expect(brandingService.update).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('resets the accent and the logo to the brand defaults only after confirmation', async () => {
    brandingService.get.mockResolvedValue(
      theme('coral', { dark: 'blob:on-dark', light: 'blob:on-light' }),
    )
    const { wrapper } = await mountView('en')
    await wrapper.find('.branding-page__actions .btn--ghost').trigger('click')
    await flushPromises()
    expect(brandingService.reset).not.toHaveBeenCalled()
    useConfirm().accept()
    await flushPromises()
    expect(brandingService.reset).toHaveBeenCalledTimes(1)
    expect(useBrandingStore().accentToken).toBe('brand_default')
    expect(useBrandingStore().logos).toEqual({ dark: null })
    wrapper
      .findAll('.logo-variant')
      .forEach((field) => expect(field.text()).toContain('Brand default'))
    wrapper.unmount()
  })

  it('keeps the saved identity when the global reset fails', async () => {
    brandingService.get.mockResolvedValue(theme('coral', { dark: 'blob:on-dark' }))
    brandingService.reset.mockRejectedValueOnce(new Error('offline'))
    const { wrapper } = await mountView('en')
    await wrapper.find('.branding-page__actions .btn--ghost').trigger('click')
    await flushPromises()
    useConfirm().accept()
    await flushPromises()
    expect(useBrandingStore().accentToken).toBe('coral')
    expect(wrapper.findAll('.logo-variant')[0].text()).toContain('Custom logo')
    expect(useToast().toasts.at(-1)).toMatchObject({
      type: 'error',
      message: 'Could not save the identity. Please try again.',
    })
    wrapper.unmount()
  })

  it('shows the server contrast_fail pairs clearly and keeps the saved accent', async () => {
    brandingService.update.mockRejectedValueOnce(
      Object.assign(new Error('contrast'), {
        normalized: {
          code: 'contrast_fail',
          message:
            'The selected color does not meet the WCAG 2.1 AA contrast ratio in light and dark mode.',
          meta: {
            pairs: [
              {
                pair: 'dark.accent_on_content',
                foreground: '#a7db4f',
                background: '#0a2325',
                ratio: '4.20',
                minimum: '4.5',
              },
            ],
          },
          fieldErrors: {},
        },
      }),
    )
    const { wrapper } = await mountView('en')
    await wrapper.findAll('.accent-swatch__input')[8].setValue(true)
    await wrapper.find('.branding-page__actions .btn--primary').trigger('click')
    await flushPromises()
    const notice = wrapper.find('.contrast-fail')
    expect(notice.attributes('role')).toBe('alert')
    expect(notice.text()).toContain('Accent as text in dark mode')
    expect(notice.text()).toMatch(/4\.2(0)?:1/)
    expect(notice.text()).toMatch(/4\.5(0)?:1/)
    expect(useBrandingStore().accentToken).toBe('brand_default')
    await wrapper.findAll('.accent-swatch__input')[2].setValue(true)
    expect(wrapper.find('.contrast-fail').exists()).toBe(false)
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
    await wrapper.findAll('.accent-swatch__input')[2].setValue(true)
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
    expect(wrapper.findAll('.accent-swatch')).toHaveLength(9)
    wrapper.unmount()
  })

  it('shows the empty state when the palette is not returned', async () => {
    brandingService.get.mockResolvedValue({
      data: { accentToken: 'brand_default', logos: { dark: null, light: null }, palette: [] },
    })
    const { wrapper } = await mountView('en')
    expect(wrapper.find('.empty-state').exists()).toBe(true)
    wrapper.unmount()
  })
})
