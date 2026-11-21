import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EntityLogoPreview from '@/components/entities/admin/EntityLogoPreview.vue'
import { createTestGlobals } from '@/test-utils'

const LOGO = {
  id: '5b0e7c1d-6a2f-4e39-9c41-0000000006a3',
  url: '/api/v1/files/5b0e7c1d/download?signature=demo',
  uploadedAt: '2026-11-03T07:20:00Z',
}

function render(props) {
  return mount(EntityLogoPreview, {
    props: { entityName: 'Al-Khair Waqf for Healthcare', refresh: vi.fn(), ...props },
    global: { plugins: createTestGlobals({ locale: 'en' }).plugins },
  })
}

afterEach(() => vi.useRealTimers())

describe('EntityLogoPreview', () => {
  it('shows the logo through the signed link with its upload date', () => {
    const wrapper = render({ logo: LOGO })
    const image = wrapper.find('img')
    expect(image.attributes('src')).toBe(LOGO.url)
    expect(image.attributes('alt')).toBe('Logo of Al-Khair Waqf for Healthcare')
    expect(wrapper.find('.entity-logo-preview__meta').text()).toContain('2026-11-03')
  })

  it('explains a missing logo', () => {
    expect(render({ logo: null }).text()).toContain('has not uploaded a logo yet')
  })

  it('hides a broken fresh link and asks a new link once the previous one expired', async () => {
    vi.useFakeTimers()
    const refresh = vi.fn().mockResolvedValue(undefined)
    const broken = render({ logo: LOGO, refresh })
    await broken.find('img').trigger('error')
    expect(refresh).not.toHaveBeenCalled()
    expect(broken.text()).toContain('cannot be shown right now')
    const expired = render({ logo: LOGO, refresh })
    vi.advanceTimersByTime(6 * 60 * 1000)
    await expired.find('img').trigger('error')
    await Promise.resolve()
    expect(refresh).toHaveBeenCalledTimes(1)
  })
})
