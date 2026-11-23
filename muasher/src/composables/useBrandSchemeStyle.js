import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useBrandingStore } from '@/stores/branding'
import { isBrandScheme } from '@/utils/brandingContrast'
import { railGradient } from '@/utils/brandRail'

export const SHELL_SELECTOR = "html:root[data-shell='tts']"

const PROPERTY_BY_KEY = Object.freeze({
  primary: 'primary',
  onPrimary: 'on-primary',
  primaryText: 'primary-text',
  primaryHover: 'primary-hover',
  primarySoft: 'primary-tint',
  secondary: 'secondary',
  onSecondary: 'on-secondary',
  railTop: 'rail-top',
  railBottom: 'rail-bottom',
  onRail: 'on-rail',
  onRailMuted: 'on-rail-muted',
  railAccent: 'rail-accent',
})

// `--brand-*` drives the shell; the visual-identity screen writes its unsaved draft as
// `--draft-*` on its own container so the rest of the page keeps the saved colours.
export function brandSchemeCss(scheme, { selector = SHELL_SELECTOR, prefix = 'brand' } = {}) {
  if (!isBrandScheme(scheme)) return ''
  const declarations = [
    ...Object.entries(PROPERTY_BY_KEY).map(
      ([key, property]) => `--${prefix}-${property}:${scheme[key]}`,
    ),
    `--${prefix}-rail-gradient:${railGradient(scheme.railStops)}`,
    `--${prefix}-rail-glow:${scheme.railGlow}%`,
  ].join(';')
  return `${selector}{${declarations}}`
}

export function useBrandSchemeStyle() {
  const { scheme } = storeToRefs(useBrandingStore())
  return computed(() => brandSchemeCss(scheme.value))
}
