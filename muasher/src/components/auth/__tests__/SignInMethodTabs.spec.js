import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import SignInMethodTabs from '@/components/auth/SignInMethodTabs.vue'
import { createTestGlobals } from '@/test-utils'

let animations

function stubReducedMotion(reduce) {
  vi.stubGlobal('matchMedia', (query) => ({
    matches: reduce && query.includes('prefers-reduced-motion'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

const Host = defineComponent({
  setup() {
    const method = ref('password')
    return () =>
      h(
        SignInMethodTabs,
        { modelValue: method.value, 'onUpdate:modelValue': (next) => (method.value = next) },
        () =>
          method.value === 'password'
            ? h('form', { key: 'password', class: 'password-form' }, 'password')
            : h('form', { key: 'code', class: 'code-form' }, 'code'),
      )
  },
})

function mountTabs() {
  const globals = createTestGlobals()
  return mount(Host, {
    global: { ...globals, stubs: { transition: false } },
    attachTo: document.body,
  })
}

async function settle() {
  await new Promise((resolve) => setTimeout(resolve))
  await flushPromises()
}

function tab(wrapper, method) {
  return wrapper.find(`[role="tab"][data-method="${method}"]`)
}

beforeEach(() => {
  animations = []
  Element.prototype.animate = vi.fn(function recordAnimate(keyframes) {
    animations.push({ target: this, shifts: keyframes.map((frame) => parseFloat(frame.translate)) })
    return { finished: Promise.resolve() }
  })
})

afterEach(() => {
  delete Element.prototype.animate
  document.body.innerHTML = ''
  document.documentElement.dir = ''
  vi.unstubAllGlobals()
})

describe('SignInMethodTabs panel motion', () => {
  it.each([
    ['ltr', 1],
    ['rtl', -1],
  ])('slides the code form in ahead along %s and back again', async (dir, ahead) => {
    stubReducedMotion(false)
    document.documentElement.dir = dir
    const wrapper = mountTabs()

    await tab(wrapper, 'code').trigger('click')
    await settle()
    const [leave, enter] = animations.splice(0)
    expect(leave.target.classList.contains('password-form')).toBe(true)
    expect(Math.sign(leave.shifts[1])).toBe(-ahead)
    expect(enter.target.classList.contains('code-form')).toBe(true)
    expect(Math.sign(enter.shifts[0])).toBe(ahead)

    await tab(wrapper, 'password').trigger('click')
    await settle()
    const [, back] = animations.splice(0)
    expect(Math.sign(back.shifts[0])).toBe(-ahead)
    wrapper.unmount()
  })

  it('tells the sliding pill which method is selected', async () => {
    stubReducedMotion(false)
    const wrapper = mountTabs()
    const list = wrapper.find('[role="tablist"]')
    expect(list.attributes('data-selected')).toBe('password')
    expect(list.find('.sign-in-methods__indicator').attributes('aria-hidden')).toBe('true')
    await tab(wrapper, 'code').trigger('click')
    expect(list.attributes('data-selected')).toBe('code')
    wrapper.unmount()
  })

  it('keeps focus on the chosen tab while the panels swap', async () => {
    stubReducedMotion(false)
    const wrapper = mountTabs()
    await tab(wrapper, 'password').trigger('keydown', { key: 'ArrowRight' })
    await settle()
    expect(wrapper.find('.code-form').exists()).toBe(true)
    expect(document.activeElement).toBe(tab(wrapper, 'code').element)
    wrapper.unmount()
  })

  it('swaps the panels at once for reduced motion', async () => {
    stubReducedMotion(true)
    const wrapper = mountTabs()
    await tab(wrapper, 'code').trigger('click')
    await settle()
    expect(wrapper.find('.code-form').exists()).toBe(true)
    expect(wrapper.find('.password-form').exists()).toBe(false)
    expect(animations).toHaveLength(0)
    wrapper.unmount()
  })
})
