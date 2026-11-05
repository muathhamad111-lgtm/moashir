import { afterEach, describe, expect, it } from 'vitest'
import { MOYASAR_FORM_ASSETS } from '@/constants/payments'
import {
  MoyasarUnavailable,
  loadMoyasarForm,
  moyasarAssetsOf,
} from '@/utils/payments/moyasarLoader'

function scripts() {
  return [...document.head.querySelectorAll('script[data-moyasar-form]')]
}

afterEach(() => {
  delete window.Moyasar
  document.head.querySelectorAll('[data-moyasar-form]').forEach((node) => node.remove())
})

describe('Moyasar form loader', () => {
  it('uses the pinned version and an SRI hash only when the checkout provides one', () => {
    expect(moyasarAssetsOf({})).toEqual({ ...MOYASAR_FORM_ASSETS, integrity: null })
    expect(MOYASAR_FORM_ASSETS.scriptUrl).toMatch(
      /^https:\/\/cdn\.moyasar\.com\/mpf\/2\.3\.0\/moyasar\.js$/,
    )
    const pinned = moyasarAssetsOf({
      script: { url: 'https://cdn.moyasar.com/mpf/2.3.0/moyasar.js', integrity: 'sha384-abc' },
    })
    expect(pinned.integrity).toBe('sha384-abc')
  })

  it('injects the script and stylesheet once, with integrity, and resolves the global', async () => {
    const assets = {
      scriptUrl: 'https://cdn.moyasar.com/mpf/2.3.0/moyasar.js',
      stylesheetUrl: MOYASAR_FORM_ASSETS.stylesheetUrl,
      integrity: 'sha384-abc',
    }
    const first = loadMoyasarForm(assets)
    const second = loadMoyasarForm(assets)
    expect(scripts()).toHaveLength(1)
    expect(scripts()[0].integrity).toBe('sha384-abc')
    expect(scripts()[0].crossOrigin).toBe('anonymous')
    expect(document.head.querySelectorAll('link[data-moyasar-form]')).toHaveLength(1)
    window.Moyasar = { init: () => null }
    scripts()[0].dispatchEvent(new Event('load'))
    await expect(first).resolves.toBe(window.Moyasar)
    await expect(second).resolves.toBe(window.Moyasar)
  })

  it('rejects when the script fails and lets a later attempt inject it again', async () => {
    const assets = {
      scriptUrl: 'https://cdn.moyasar.com/mpf/2.3.0/retry/moyasar.js',
      stylesheetUrl: MOYASAR_FORM_ASSETS.stylesheetUrl,
      integrity: null,
    }
    const failing = loadMoyasarForm(assets)
    expect(scripts()[0].hasAttribute('integrity')).toBe(false)
    scripts()[0].dispatchEvent(new Event('error'))
    await expect(failing).rejects.toBeInstanceOf(MoyasarUnavailable)
    expect(scripts()).toHaveLength(0)
    loadMoyasarForm(assets)
    expect(scripts()).toHaveLength(1)
  })
})
