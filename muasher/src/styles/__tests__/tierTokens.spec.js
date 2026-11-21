import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { contrastRatio } from '@/utils/contrast'

const AA_TEXT = 4.5
const AA_GRAPHIC = 3
const TIERS = ['bronze', 'silver', 'gold', 'diamond', 'neutral']
const WHITE = '#ffffff'
const DARK_PAGE = '#0a2325'
const LIGHT_INKS = ['data-ink', 'delta-up-ink', 'delta-down-ink']
const DARK_INKS = ['data-ink-dark', 'delta-up-ink-dark', 'delta-down-ink-dark']

function readStyle(relative) {
  return readFileSync(resolve(process.cwd(), 'src/styles', relative), 'utf8')
}

function blockOf(source, name) {
  return source.split(`$${name}: (`)[1].split(');')[0]
}

function paletteOf(source, name) {
  const block = blockOf(source, name)
  return Object.fromEntries(
    TIERS.map((tier) => {
      const body = block.split(`'${tier}': (`)[1].split(')')[0]
      const roles = [...body.matchAll(/'([a-z]+)': (#[0-9a-f]{6})/g)]
      return [tier, Object.fromEntries(roles.map(([, role, hex]) => [role, hex]))]
    }),
  )
}

function mapValue(source, name, key) {
  return new RegExp(`'${key}': (#[0-9a-f]{6})`).exec(blockOf(source, name))[1]
}

function scalar(source, name) {
  return new RegExp(`\\$${name}: (#[0-9a-f]{6});`).exec(source)[1]
}

const tiers = readStyle('tokens/_tiers.scss')
const shell = readStyle('tokens/_shell-b.scss')
const palettes = {
  light: paletteOf(tiers, 'tier-palette-light'),
  dark: paletteOf(tiers, 'tier-palette-dark'),
}
const bandStops = ['stage-light', 'stage-dark', 'nav-light', 'nav-dark'].flatMap((name) => [
  mapValue(shell, name, 'from'),
  mapValue(shell, name, 'to'),
])

describe('tier tokens', () => {
  it.each(['light', 'dark'])('defines every role for every tier in %s mode', (mode) => {
    TIERS.forEach((tier) =>
      expect(Object.keys(palettes[mode][tier])).toEqual([
        'surface',
        'border',
        'ink',
        'accent',
        'graphic',
      ]),
    )
  })

  it.each(['light', 'dark'])('keeps the level name ink at AA on the tier surface (%s)', (mode) => {
    TIERS.forEach((tier) => {
      const { ink, surface } = palettes[mode][tier]
      expect(contrastRatio(ink, surface)).toBeGreaterThanOrEqual(AA_TEXT)
    })
  })

  it.each(['light', 'dark'])('keeps the tier graphic at 3:1 on the tier surface (%s)', (mode) => {
    TIERS.forEach((tier) => {
      const { graphic, surface } = palettes[mode][tier]
      expect(contrastRatio(graphic, surface)).toBeGreaterThanOrEqual(AA_GRAPHIC)
    })
  })
})

describe('dark band tokens', () => {
  it('keeps white text, muted band text and the band data colour at AA on every band stop', () => {
    const inks = [WHITE, scalar(shell, 'data-on-band'), scalar(shell, 'band-ink-muted')]
    bandStops.forEach((stop) =>
      inks.forEach((ink) => expect(contrastRatio(ink, stop)).toBeGreaterThanOrEqual(AA_TEXT)),
    )
  })

  it('keeps the dark tier ink readable on the band', () => {
    bandStops.forEach((stop) =>
      TIERS.forEach((tier) =>
        expect(contrastRatio(palettes.dark[tier].ink, stop)).toBeGreaterThanOrEqual(AA_TEXT),
      ),
    )
  })

  it('keeps the data and delta inks at AA on a white card', () => {
    LIGHT_INKS.forEach((name) =>
      expect(contrastRatio(scalar(shell, name), WHITE)).toBeGreaterThanOrEqual(AA_TEXT),
    )
  })

  it('keeps the dark-mode data and delta inks at AA on the dark page', () => {
    DARK_INKS.forEach((name) =>
      expect(contrastRatio(scalar(shell, name), DARK_PAGE)).toBeGreaterThanOrEqual(AA_TEXT),
    )
  })
})
