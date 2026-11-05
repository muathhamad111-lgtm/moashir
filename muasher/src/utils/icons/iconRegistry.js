import pending from '@/assets/icons/tts/pending.json'
import { ICON_ALIASES, RTL_MIRRORED_ICONS } from '@/constants/icons'
import { iconNameFromStored } from './storedIcon'
import { svgPathData } from './svgPathData'

const SVG_SOURCES = import.meta.glob('../../assets/icons/tts/*.svg', {
  eager: true,
  query: '?raw',
  import: 'default',
})

const INTERIM_SOURCES = import.meta.glob('../../assets/icons/interim/*.svg', {
  eager: true,
  query: '?raw',
  import: 'default',
})

function iconNameOfFile(path) {
  return path.slice(path.lastIndexOf('/') + 1, -'.svg'.length)
}

function glyphsOf(sources) {
  return Object.freeze(
    Object.fromEntries(
      Object.entries(sources).map(([path, source]) => [iconNameOfFile(path), svgPathData(source)]),
    ),
  )
}

export const ICON_GLYPHS = glyphsOf(SVG_SOURCES)

export const INTERIM_GLYPHS = glyphsOf(INTERIM_SOURCES)

export const PENDING_ICONS = Object.freeze({ ...pending.glyph, ...pending.brand })

export const WITHHELD_ICONS = Object.freeze([...pending.withheld])

export const ICON_NAMES = Object.freeze(
  [
    ...new Set([
      ...Object.keys(ICON_GLYPHS),
      ...Object.keys(ICON_ALIASES),
      ...Object.keys(INTERIM_GLYPHS),
      ...Object.keys(PENDING_ICONS),
    ]),
  ].sort(),
)

export const PICKABLE_ICON_NAMES = Object.freeze(
  [...new Set([...Object.keys(ICON_GLYPHS), ...Object.keys(pending.glyph)])].sort(),
)

const PLAIN = Object.freeze({ flipped: false, spinning: false })

function aliasGlyph({ glyph, flipped, spinning }) {
  return { flipped, spinning, paths: ICON_GLYPHS[glyph] }
}

function glyphOf(name) {
  if (ICON_GLYPHS[name]) return { ...PLAIN, paths: ICON_GLYPHS[name] }
  if (ICON_ALIASES[name]) return aliasGlyph(ICON_ALIASES[name])
  if (INTERIM_GLYPHS[name]) return { ...PLAIN, paths: INTERIM_GLYPHS[name] }
  const fallback = PENDING_ICONS[name]
  return fallback ? { ...PLAIN, paths: ICON_GLYPHS[fallback] ?? INTERIM_GLYPHS[fallback] } : null
}

export function resolveIcon(value) {
  const name = iconNameFromStored(value)
  const glyph = name ? glyphOf(name) : null
  if (!glyph?.paths?.length) return null
  return {
    name,
    paths: glyph.paths,
    flipped: glyph.flipped,
    spinning: glyph.spinning,
    mirrorInRtl: RTL_MIRRORED_ICONS.includes(name),
  }
}

export function hasIcon(value) {
  return resolveIcon(value) !== null
}
