import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { afterEach, describe, expect, it } from 'vitest'
import {
  MoyasarUnavailable,
  loadMoyasarForm,
  moyasarAssetsOf,
  stylesheetUrlOf,
} from '@/utils/payments/moyasarLoader'

const SERVED_SCRIPT = 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.js'
const SERVED_STYLESHEET = 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.css'

function scripts() {
  return [...document.head.querySelectorAll('script[data-moyasar-form]')]
}

function stylesheets() {
  return [...document.head.querySelectorAll('link[data-moyasar-form]')]
}

afterEach(() => {
  delete window.Moyasar
  document.head.querySelectorAll('[data-moyasar-form]').forEach((node) => node.remove())
})

describe('Moyasar form loader', () => {
  it('takes the script from the checkout and the stylesheet of the same version', () => {
    expect(moyasarAssetsOf({ script: { url: SERVED_SCRIPT, integrity: null } })).toEqual({
      scriptUrl: SERVED_SCRIPT,
      integrity: null,
      stylesheetUrl: SERVED_STYLESHEET,
    })
    expect(
      moyasarAssetsOf({
        script: { url: '/vendor/moyasar/1.15.0/moyasar.js', integrity: 'sha384-abc' },
      }),
    ).toEqual({
      scriptUrl: '/vendor/moyasar/1.15.0/moyasar.js',
      integrity: 'sha384-abc',
      stylesheetUrl: '/vendor/moyasar/1.15.0/moyasar.css',
    })
    expect(stylesheetUrlOf('https://cdn.moyasar.com/mpf/1.15.0/moyasar.js?v=2')).toBe(
      'https://cdn.moyasar.com/mpf/1.15.0/moyasar.css?v=2',
    )
    expect(stylesheetUrlOf('https://cdn.example.sa/form')).toBeNull()
  })

  it('has no hard-coded form version left in the application code', () => {
    const constants = readFileSync(resolve(process.cwd(), 'src/constants/payments.js'), 'utf8')
    expect(constants).not.toMatch(/MOYASAR_FORM_VERSION|mpf\/\d/)
    expect(moyasarAssetsOf({})).toEqual({ scriptUrl: null, integrity: null, stylesheetUrl: null })
  })

  it('refuses to load when the checkout names no script', async () => {
    await expect(loadMoyasarForm(moyasarAssetsOf({}))).rejects.toBeInstanceOf(MoyasarUnavailable)
    expect(scripts()).toHaveLength(0)
    expect(stylesheets()).toHaveLength(0)
  })

  it('injects the script and stylesheet once, with integrity, and resolves the global', async () => {
    const assets = moyasarAssetsOf({ script: { url: SERVED_SCRIPT, integrity: 'sha384-abc' } })
    const first = loadMoyasarForm(assets)
    const second = loadMoyasarForm(assets)
    expect(scripts()).toHaveLength(1)
    expect(scripts()[0].src).toBe(SERVED_SCRIPT)
    expect(scripts()[0].integrity).toBe('sha384-abc')
    expect(scripts()[0].crossOrigin).toBe('anonymous')
    expect(stylesheets().map((link) => link.href)).toEqual([SERVED_STYLESHEET])
    window.Moyasar = { init: () => null }
    scripts()[0].dispatchEvent(new Event('load'))
    await expect(first).resolves.toBe(window.Moyasar)
    await expect(second).resolves.toBe(window.Moyasar)
  })

  it('loads without integrity or CORS mode when the backend sends none', () => {
    const url = 'https://cdn.moyasar.com/mpf/1.15.0/plain/moyasar.js'
    loadMoyasarForm(moyasarAssetsOf({ script: { url, integrity: null } }))
    expect(scripts()[0].hasAttribute('integrity')).toBe(false)
    expect(scripts()[0].hasAttribute('crossorigin')).toBe(false)
  })

  it('rejects when the script fails and lets a later attempt inject it again', async () => {
    const assets = moyasarAssetsOf({
      script: { url: 'https://cdn.moyasar.com/mpf/1.15.0/retry/moyasar.js', integrity: null },
    })
    const failing = loadMoyasarForm(assets)
    scripts()[0].dispatchEvent(new Event('error'))
    await expect(failing).rejects.toBeInstanceOf(MoyasarUnavailable)
    expect(scripts()).toHaveLength(0)
    loadMoyasarForm(assets)
    expect(scripts()).toHaveLength(1)
  })
})
