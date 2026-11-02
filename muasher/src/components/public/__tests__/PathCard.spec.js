import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PathCard from '@/components/public/PathCard.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

const path = {
  key: 'accredited',
  icon: 'fa-solid fa-clipboard-check',
  title: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  summary: { ar: 'ملخص', en: 'Summary' },
  order: 2,
}

function localized(content) {
  return typeof content === 'string' ? content : content.en
}

function mountCard(pathOverride = path) {
  const globals = createTestGlobals({ locale: 'en' })
  const wrapper = mount(PathCard, {
    props: { path: pathOverride, localized },
    global: { plugins: globals.plugins },
  })
  return { wrapper, router: globals.router }
}

beforeEach(() => {
  localStorage.clear()
})

describe('PathCard', () => {
  it('renders the localized title and summary', () => {
    const { wrapper } = mountCard()
    expect(wrapper.find('.path-card__title').text()).toBe('Accredited assessment')
    expect(wrapper.find('.path-card__summary').text()).toBe('Summary')
  })

  it('routes an anonymous visitor to login with the path as redirect', async () => {
    const { wrapper, router } = mountCard()
    const push = vi.spyOn(router, 'push')
    await wrapper.find('.path-card__cta button').trigger('click')
    expect(push).toHaveBeenCalledWith({
      name: ROUTE_NAMES.AUTH_LOGIN,
      query: { redirect: 'accredited' },
    })
  })

  it('routes an authenticated visitor straight to the assessment path', async () => {
    const { wrapper, router } = mountCard()
    const auth = useAuthStore()
    auth.setUser({ name: 'Entity', roles: ['entity'] })
    const push = vi.spyOn(router, 'push')
    await wrapper.find('.path-card__cta button').trigger('click')
    expect(push).toHaveBeenCalledWith({ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED })
  })
})
