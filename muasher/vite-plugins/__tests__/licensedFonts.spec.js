import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'
import {
  ABSENT_FONTS_ID,
  LICENSED_FONTS_ID,
  LICENSED_FONTS_STYLESHEET,
  LICENSED_FONT_FILES,
  REQUIRE_FONTS_ENV,
  licensedFonts,
} from '../licensedFonts'

const roots = []

function projectRoot(fontFiles) {
  const root = mkdtempSync(join(tmpdir(), 'moashir-fonts-'))
  fontFiles.forEach((file) => {
    mkdirSync(dirname(join(root, file)), { recursive: true })
    writeFileSync(join(root, file), 'wOFF')
  })
  roots.push(root)
  return root
}

function started(fontFiles, env = {}) {
  const root = projectRoot(fontFiles)
  const plugin = licensedFonts(env)
  plugin.configResolved({ root })
  return { plugin, root }
}

afterEach(() => {
  roots.splice(0).forEach((root) => rmSync(root, { recursive: true, force: true }))
})

describe('licensedFonts', () => {
  it('serves the Hacen Maghreb faces when every font file is present', () => {
    const { plugin, root } = started(LICENSED_FONT_FILES)

    expect(plugin.resolveId(LICENSED_FONTS_ID)).toBe(resolve(root, LICENSED_FONTS_STYLESHEET))
  })

  it('serves no faces when a font file is missing, so the fallback font is used', () => {
    const { plugin } = started(LICENSED_FONT_FILES.slice(1))

    expect(plugin.resolveId(LICENSED_FONTS_ID)).toBe(ABSENT_FONTS_ID)
    expect(plugin.load(ABSENT_FONTS_ID)).toBe('')
  })

  it('leaves every other import to the other resolvers', () => {
    const { plugin } = started(LICENSED_FONT_FILES)

    expect(plugin.resolveId('@/styles/main.scss')).toBeNull()
    expect(plugin.load('/src/main.js')).toBeNull()
  })

  it('fails a build that requires the fonts when they are missing', () => {
    const plugin = licensedFonts({ [REQUIRE_FONTS_ENV]: 'true' })

    expect(() => plugin.configResolved({ root: projectRoot([]) })).toThrow(/licensed fonts/)
  })

  it('passes a build that requires the fonts when they are present', () => {
    expect(() => started(LICENSED_FONT_FILES, { [REQUIRE_FONTS_ENV]: 'true' })).not.toThrow()
  })
})
