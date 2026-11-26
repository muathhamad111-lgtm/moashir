import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useAppUpdateCheck } from '@/composables/useAppUpdateCheck'
import { rememberBootBundle } from '@/utils/bundleSignature'
import { APP_UPDATE_MIN_GAP_MS, APP_UPDATE_POLL_MS } from '@/constants/appUpdate'

function page(entry) {
  return `<html><head><script type="module" src="/assets/${entry}.js"></script></head></html>`
}

function boot(entry) {
  rememberBootBundle(new DOMParser().parseFromString(page(entry), 'text/html'))
}

function mountCheck(fetcher, now = () => Date.now()) {
  let api
  const Host = defineComponent({
    setup() {
      api = useAppUpdateCheck({ fetcher, now })
      return () => h('div')
    },
  })
  const wrapper = mount(Host)
  return { wrapper, api }
}

function serving(entry) {
  return vi.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve(page(entry)) })
}

afterEach(() => {
  vi.useRealTimers()
})

describe('new version check', () => {
  it('stays quiet while the deployed page lists the same bundle', async () => {
    boot('index-AAA')
    const fetcher = serving('index-AAA')
    const { api } = mountCheck(fetcher)
    await api.check()
    expect(fetcher).toHaveBeenCalledWith('/index.html', { cache: 'no-store' })
    expect(api.updateAvailable.value).toBe(false)
  })

  it('offers the update once the deployed page lists a newer bundle, then stops asking', async () => {
    boot('index-AAA')
    const fetcher = serving('index-BBB')
    const { api } = mountCheck(fetcher)
    await api.check()
    await api.check()
    expect(api.updateAvailable.value).toBe(true)
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('checks every few minutes and when the tab comes back into focus, at most once a minute', async () => {
    vi.useFakeTimers()
    boot('index-AAA')
    const fetcher = serving('index-AAA')
    let clock = 10 * APP_UPDATE_MIN_GAP_MS
    mountCheck(fetcher, () => clock)

    window.dispatchEvent(new Event('focus'))
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    expect(fetcher).toHaveBeenCalledTimes(1)

    clock += APP_UPDATE_MIN_GAP_MS
    window.dispatchEvent(new Event('focus'))
    await flushPromises()
    expect(fetcher).toHaveBeenCalledTimes(2)

    await vi.advanceTimersByTimeAsync(APP_UPDATE_POLL_MS)
    expect(fetcher).toHaveBeenCalledTimes(3)
  })

  it('keeps working when the check fails, and never runs on a development page', async () => {
    boot('index-AAA')
    const failing = vi.fn().mockRejectedValue(new Error('offline'))
    const { api } = mountCheck(failing)
    await api.check()
    expect(api.updateAvailable.value).toBe(false)

    rememberBootBundle(new DOMParser().parseFromString('<html></html>', 'text/html'))
    const fetcher = serving('index-BBB')
    const dev = mountCheck(fetcher)
    await dev.api.check()
    window.dispatchEvent(new Event('focus'))
    expect(fetcher).not.toHaveBeenCalled()
  })
})
