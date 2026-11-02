import { describe, expect, it } from 'vitest'
import { fontAwesomeKitScripts, fontAwesomeSource } from '@/plugins/fontAwesome'
import {
  FONT_AWESOME_MODULE_ID,
  fontAwesome,
  fontAwesomeModuleSource,
  isFontAwesomeProInstalled,
} from '../../../vite-plugins/fontAwesome'

describe('Font Awesome source switch', () => {
  it('reports the source detected from the installed packages', () => {
    expect(fontAwesomeSource).toBe(isFontAwesomeProInstalled() ? 'self-hosted' : 'kit')
  })

  it('never injects the kit script when the Pro package is self-hosted', () => {
    expect(fontAwesomeKitScripts('self-hosted', 'abc123')).toEqual([])
  })

  it('keeps the kit only as the fallback and only when its id is configured', () => {
    expect(fontAwesomeKitScripts('kit', '')).toEqual([])
    expect(fontAwesomeKitScripts('kit', 'abc123')).toEqual([
      { src: 'https://kit.fontawesome.com/abc123.js', crossorigin: 'anonymous', defer: true },
    ])
  })

  it('serves a virtual module that imports the bundled styles only in self-hosted mode', () => {
    expect(fontAwesomeModuleSource(true)).toContain("import '/src/styles/vendor/font-awesome.scss'")
    expect(fontAwesomeModuleSource(true)).toContain("'self-hosted'")
    expect(fontAwesomeModuleSource(false)).not.toContain('import')
    const plugin = fontAwesome({ selfHosted: false })
    const resolved = plugin.resolveId(FONT_AWESOME_MODULE_ID)
    expect(plugin.load(resolved)).toBe("export const fontAwesomeSource = 'kit'\n")
    expect(plugin.resolveId('vue')).toBeNull()
    expect(plugin.load('vue')).toBeNull()
  })

  it('detects a missing package without throwing', () => {
    expect(isFontAwesomeProInstalled('file:///nonexistent/dir/index.js')).toBe(false)
  })
})
