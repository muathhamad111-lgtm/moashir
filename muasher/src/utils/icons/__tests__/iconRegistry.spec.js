import { readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import pending from '@/assets/icons/tts/pending.json'
import { ICON_ALIASES, RTL_MIRRORED_ICONS } from '@/constants/icons'
import { KPI_ICON_OPTIONS } from '@/constants/kpis'
import {
  ICON_GLYPHS,
  ICON_NAMES,
  INTERIM_GLYPHS,
  PENDING_ICONS,
  PICKABLE_ICON_NAMES,
  WITHHELD_ICONS,
  hasIcon,
  resolveIcon,
} from '@/utils/icons/iconRegistry'
import { LEGACY_ICON_NAMES } from '@/utils/icons/legacyIconNames'

const ICON_DIR = resolve(process.cwd(), 'src/assets/icons/tts')
const SVG_FILES = readdirSync(ICON_DIR)
  .filter((file) => file.endsWith('.svg'))
  .map((file) => file.slice(0, -'.svg'.length))

describe('icon registry', () => {
  it('registers every TTS SVG with its path data', () => {
    expect(Object.keys(ICON_GLYPHS).sort()).toEqual([...SVG_FILES].sort())
    Object.values(ICON_GLYPHS).forEach((paths) => {
      expect(paths.length).toBeGreaterThan(0)
      paths.forEach((path) => expect(path).toMatch(/^M/))
    })
  })

  it('points every pending icon to an existing stop-gap glyph', () => {
    const missing = Object.entries(PENDING_ICONS).filter(
      ([, fallback]) => !ICON_GLYPHS[fallback] && !INTERIM_GLYPHS[fallback],
    )
    expect(missing).toEqual([])
  })

  it('lists only icons that still have no SVG as pending, so publishing one is a data-only swap', () => {
    Object.keys(PENDING_ICONS).forEach((name) => expect(SVG_FILES).not.toContain(name))
    Object.keys(INTERIM_GLYPHS).forEach((name) => expect(SVG_FILES).not.toContain(name))
    WITHHELD_ICONS.forEach((name) => expect(SVG_FILES).not.toContain(name))
    Object.keys(pending.brand).forEach((name) => expect(name).toMatch(/^brand-/))
  })

  it('draws nothing for the withheld names whose stand-in would mislead', () => {
    expect(WITHHELD_ICONS).toEqual(['flask', 'language', 'pause', 'phone'])
    WITHHELD_ICONS.forEach((name) => {
      expect(PENDING_ICONS).not.toHaveProperty(name)
      expect(resolveIcon(name)).toBeNull()
      expect(hasIcon(name)).toBe(false)
      expect(PICKABLE_ICON_NAMES).not.toContain(name)
    })
  })

  it('draws the interim dot-less bell, derived from the TTS bell with the dot', () => {
    expect(resolveIcon('bell').paths).toEqual(INTERIM_GLYPHS.bell)
    expect(INTERIM_GLYPHS.bell).not.toEqual(ICON_GLYPHS['bell-dot'])
    expect(resolveIcon('bell-off').paths).toEqual(INTERIM_GLYPHS.bell)
    expect(hasIcon('bell-dot')).toBe(true)
  })

  it('resolves every registered name, alias and mirrored name to a glyph', () => {
    expect(ICON_NAMES.filter((name) => !resolveIcon(name)?.paths.length)).toEqual([])
    Object.values(ICON_ALIASES).forEach(({ glyph }) => expect(ICON_GLYPHS[glyph]).toBeDefined())
    RTL_MIRRORED_ICONS.forEach((name) => expect(ICON_NAMES).toContain(name))
  })

  it('resolves every legacy name the server may still store', () => {
    const unresolved = Object.entries(LEGACY_ICON_NAMES).filter(
      ([legacy, name]) =>
        !WITHHELD_ICONS.includes(name) && resolveIcon(`fa-solid fa-${legacy}`)?.name !== name,
    )
    expect(unresolved).toEqual([])
  })

  it('offers drawable glyphs only in the icon pickers', () => {
    expect(PICKABLE_ICON_NAMES).toContain('award')
    expect(PICKABLE_ICON_NAMES).toContain('search')
    expect(PICKABLE_ICON_NAMES.some((name) => name.startsWith('brand-'))).toBe(false)
    expect(PICKABLE_ICON_NAMES).not.toContain('spinner')
    KPI_ICON_OPTIONS.forEach((name) => expect(PICKABLE_ICON_NAMES).toContain(name))
    expect(new Set(KPI_ICON_OPTIONS).size).toBe(KPI_ICON_OPTIONS.length)
  })
})
