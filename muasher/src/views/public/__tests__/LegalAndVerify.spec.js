import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import LegalPage from '@/views/public/LegalPage.vue'
import Verify from '@/views/public/Verify.vue'
import { legalService } from '@/services/legalService'
import { contentService } from '@/services/contentService'
import { certificatesService } from '@/services/certificatesService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/legalService', () => ({ legalService: { getCurrent: vi.fn() } }))
vi.mock('@/services/contentService', () => ({ contentService: { getPage: vi.fn() } }))
vi.mock('@/services/certificatesService', () => ({ certificatesService: { verify: vi.fn() } }))

async function mountView(component, { props = {}, path = '/' } = {}) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  await globals.router.push(path)
  const wrapper = mount(component, { props, global: globals })
  await flushPromises()
  return { wrapper, router: globals.router }
}

beforeEach(() => vi.clearAllMocks())

describe('LegalPage', () => {
  it('shows the current version, the update date and the blocks of the active locale', async () => {
    legalService.getCurrent.mockResolvedValue({
      data: {
        kind: 'privacy',
        version: '2026-11-draft',
        publishedAt: '2026-11-04T11:20:05+00:00',
        title: { ar: 'سياسة الخصوصية — منصة مؤشر', en: 'Privacy Policy' },
        body: { ar: [{ type: 'heading', level: 2, text: '1. مقدمة' }], en: [] },
      },
    })
    const { wrapper } = await mountView(LegalPage, { props: { kind: 'privacy' } })
    expect(wrapper.find('h1').text()).toBe('سياسة الخصوصية — منصة مؤشر')
    expect(wrapper.find('.legal-page__meta').text()).toContain('2026-11-draft')
    expect(wrapper.find('.legal-page__meta').text()).toContain('2026-11-04')
    expect(wrapper.find('h2').text()).toBe('1. مقدمة')
  })

  it('says when a text is not published and renders the accessibility page sections', async () => {
    legalService.getCurrent.mockRejectedValue(
      Object.assign(new Error('404'), { normalized: { status: 404 } }),
    )
    const missing = await mountView(LegalPage, { props: { kind: 'terms' } })
    expect(missing.wrapper.find('.empty-state').text()).toContain('شروط الاستخدام')
    contentService.getPage.mockResolvedValue({
      data: {
        title: { ar: 'بيان إمكانية الوصول', en: 'Accessibility' },
        updatedAt: '2026-11-04T11:20:04+00:00',
        sections: [
          {
            id: 93,
            title: { ar: '2. المعيار المعتمد', en: '2. Standard' },
            blocks: { ar: [{ type: 'heading', level: 2, text: 'تفاصيل' }], en: [] },
          },
        ],
      },
    })
    const page = await mountView(LegalPage, { props: { kind: 'accessibility' } })
    expect(page.wrapper.find('h2').text()).toBe('2. المعيار المعتمد')
    expect(page.wrapper.find('h3').text()).toBe('تفاصيل')
  })
})

describe('Verify', () => {
  it('normalizes the typed code and opens its result page', async () => {
    certificatesService.verify.mockResolvedValue({
      data: { result: 'not_found', certificate: null },
    })
    const { wrapper, router } = await mountView(Verify, { path: '/verify' })
    expect(certificatesService.verify).not.toHaveBeenCalled()
    await wrapper.find('input').setValue('  9f3k-mq2t ')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.params.code).toBe('9F3K-MQ2T')
  })

  it('shows the result of the code in the address and the rate limit message', async () => {
    certificatesService.verify.mockResolvedValueOnce({
      data: { result: 'revoked', certificate: { number: 'CRT-2026-000002', entityName: null } },
    })
    const revoked = await mountView(Verify, { path: '/verify/B4NE-2WQ8' })
    expect(certificatesService.verify).toHaveBeenCalledWith('B4NE-2WQ8')
    expect(revoked.wrapper.find('.verification-result--revoked').exists()).toBe(true)
    certificatesService.verify.mockRejectedValueOnce(
      Object.assign(new Error('429'), { normalized: { status: 429 } }),
    )
    const limited = await mountView(Verify, { path: '/verify/B4NE-2WQ8' })
    expect(limited.wrapper.find('.verify-page__alert').exists()).toBe(true)
  })
})
