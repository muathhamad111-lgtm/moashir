import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { contrastRatio } from '@/utils/contrast'

const AA_TEXT = 4.5
const CLASSES = ['strength', 'opportunity', 'critical']
const BANDS = ['emerging', 'developing', 'mature', 'leading']
const source = readFileSync(resolve(process.cwd(), 'src/styles/tokens/_insights.scss'), 'utf8')

function paletteOf(name, keys) {
  const block = source.split(`$${name}: (`)[1].split(');')[0]
  return Object.fromEntries(
    keys.map((key) => {
      const body = block.split(`'${key}': (`)[1].split(')')[0]
      const roles = [...body.matchAll(/'([a-z]+)': (#[0-9a-f]{6})/g)]
      return [key, Object.fromEntries(roles.map(([, role, hex]) => [role, hex]))]
    }),
  )
}

describe('excellence tools tokens', () => {
  it.each(['light', 'dark'])('keeps every class chip ink at AA on its surface (%s)', (mode) => {
    const palette = paletteOf(`insight-class-${mode}`, CLASSES)
    CLASSES.forEach((key) =>
      expect(contrastRatio(palette[key].ink, palette[key].surface)).toBeGreaterThanOrEqual(AA_TEXT),
    )
  })

  it('keeps the dark critical-gap chip at AA', () => {
    const { ink, surface } = paletteOf('insight-class-dark', CLASSES).critical
    expect(contrastRatio(ink, surface)).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it.each(['light', 'dark'])('keeps every maturity band chip at AA (%s)', (mode) => {
    const palette = paletteOf(`insight-band-${mode}`, BANDS)
    BANDS.forEach((key) =>
      expect(contrastRatio(palette[key].ink, palette[key].surface)).toBeGreaterThanOrEqual(AA_TEXT),
    )
  })

  it('uses a sequential neutral band palette from light to dark in light mode', () => {
    const palette = paletteOf('insight-band-light', BANDS)
    const contrasts = BANDS.map((key) => contrastRatio(palette[key].surface, '#ffffff'))
    expect(contrasts).toEqual([...contrasts].sort((a, b) => a - b))
  })
})
