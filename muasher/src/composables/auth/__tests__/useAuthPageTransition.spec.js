import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, RouterLink, RouterView } from 'vue-router'
import { flushPromises, mount } from '@vue/test-utils'
import { authSwitchStep } from '@/composables/auth/useAuthPageTransition'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { createTestGlobals } from '@/test-utils'

function stubReducedMotion(reduce) {
  vi.stubGlobal('matchMedia', (query) => ({
    matches: reduce && query.includes('prefers-reduced-motion'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

function codePage() {
  return defineComponent({
    name: 'AuthPageCode',
    render: () =>
      h('section', { class: 'auth-form' }, [
        h('h1', 'Code'),
        h('div', { 'data-auth-autofocus': '', role: 'group' }, [
          h('input', { class: 'code-box' }),
          h('input', { class: 'code-box' }),
        ]),
        h(RouterLink, { to: { name: ROUTE_NAMES.AUTH_LOGIN }, class: 'switch-link' }, () => 'back'),
      ]),
  })
}

function authPage(title, linkTo) {
  return defineComponent({
    name: `AuthPage${title}`,
    render: () =>
      h('section', { class: 'auth-form' }, [
        h('h1', title),
        h(RouterLink, { to: { name: linkTo }, class: 'switch-link' }, () => 'switch'),
      ]),
  })
}

async function mountAuthFlow() {
  const globals = createTestGlobals()
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/auth/login',
        name: ROUTE_NAMES.AUTH_LOGIN,
        component: authPage('Sign in', ROUTE_NAMES.AUTH_REGISTER),
      },
      {
        path: '/auth/register',
        name: ROUTE_NAMES.AUTH_REGISTER,
        component: authPage('Register', ROUTE_NAMES.AUTH_LOGIN),
      },
      {
        path: '/auth/otp',
        name: ROUTE_NAMES.AUTH_OTP,
        component: codePage(),
      },
      {
        path: '/',
        name: ROUTE_NAMES.PUBLIC_HOME,
        component: authPage('Home', ROUTE_NAMES.AUTH_LOGIN),
      },
    ],
  })
  await router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
  const Host = defineComponent({
    render: () =>
      h(RouterView, null, {
        default: ({ Component }) => h(AuthLayout, null, () => (Component ? h(Component) : null)),
      }),
  })
  const wrapper = mount(Host, {
    global: {
      plugins: [globals.i18n, router, globals.pinia, globals.head],
      stubs: { transition: false },
    },
    attachTo: document.body,
  })
  await flushPromises()
  return { wrapper, router }
}

describe('authSwitchStep', () => {
  afterEach(() => vi.unstubAllGlobals())

  it.each([
    [ROUTE_NAMES.AUTH_LOGIN, ROUTE_NAMES.AUTH_REGISTER, 1],
    [ROUTE_NAMES.AUTH_LOGIN, ROUTE_NAMES.AUTH_FORGOT_PASSWORD, 1],
    [ROUTE_NAMES.AUTH_REGISTER, ROUTE_NAMES.AUTH_LOGIN, -1],
    [ROUTE_NAMES.AUTH_FORGOT_PASSWORD, ROUTE_NAMES.AUTH_LOGIN, -1],
    [ROUTE_NAMES.AUTH_LOGIN, ROUTE_NAMES.AUTH_OTP, 1],
    [ROUTE_NAMES.AUTH_OTP, ROUTE_NAMES.AUTH_LOGIN, -1],
    [ROUTE_NAMES.AUTH_LOGIN, ROUTE_NAMES.AUTH_MFA, 1],
    [ROUTE_NAMES.AUTH_MFA, ROUTE_NAMES.AUTH_LOGIN, -1],
    [ROUTE_NAMES.PUBLIC_HOME, ROUTE_NAMES.AUTH_LOGIN, 0],
  ])('moves from %s to %s with step %i', (from, to, step) => {
    stubReducedMotion(false)
    expect(authSwitchStep(from, to)).toBe(step)
  })

  it('keeps every switch still when the visitor asks for reduced motion', () => {
    stubReducedMotion(true)
    expect(authSwitchStep(ROUTE_NAMES.AUTH_LOGIN, ROUTE_NAMES.AUTH_REGISTER)).toBe(0)
  })
})

describe('useAuthPageTransition in the auth layout', () => {
  afterEach(() => {
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it.each([false, true])(
    'swaps the page and moves focus from the used link to the new heading (reduced motion: %s)',
    async (reduce) => {
      stubReducedMotion(reduce)
      const { wrapper } = await mountAuthFlow()
      const link = wrapper.find('.switch-link')
      link.element.focus()
      await link.trigger('click')
      await flushPromises()
      const heading = wrapper.find('.auth-layout__page h1')
      expect(heading.text()).toBe('Register')
      expect(heading.attributes('tabindex')).toBe('-1')
      expect(document.activeElement).toBe(heading.element)
      wrapper.unmount()
    },
  )

  it.each([false, true])(
    'moves focus to the first code box after a sign-in submit (reduced motion: %s)',
    async (reduce) => {
      stubReducedMotion(reduce)
      const { wrapper, router } = await mountAuthFlow()
      wrapper.find('.switch-link').element.focus()
      await router.push({ name: ROUTE_NAMES.AUTH_OTP })
      await flushPromises()
      const boxes = wrapper.findAll('.code-box')
      expect(document.activeElement).toBe(boxes[0].element)
      wrapper.unmount()
    },
  )

  it('swaps pages outside the switch too, without pulling focus from outside the card', async () => {
    stubReducedMotion(false)
    const { wrapper, router } = await mountAuthFlow()
    const outside = document.createElement('button')
    document.body.append(outside)
    outside.focus()
    await router.push({ name: ROUTE_NAMES.AUTH_OTP })
    await flushPromises()
    expect(wrapper.find('.auth-layout__page h1').text()).toBe('Code')
    expect(document.activeElement).toBe(outside)
    wrapper.unmount()
  })
})
