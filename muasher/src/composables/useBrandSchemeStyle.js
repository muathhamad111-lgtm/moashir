import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useBrandingStore } from '@/stores/branding'
import { isBrandScheme } from '@/utils/brandingContrast'

const SHELL_SELECTOR = "html:root[data-shell='tts']"

const PROPERTY_BY_KEY = Object.freeze({
  primary: '--brand-primary',
  onPrimary: '--brand-on-primary',
  primaryText: '--brand-primary-text',
  secondary: '--brand-secondary',
  onSecondary: '--brand-on-secondary',
})

export function brandSchemeCss(scheme) {
  if (!isBrandScheme(scheme)) return ''
  const declarations = Object.entries(PROPERTY_BY_KEY)
    .map(([key, property]) => `${property}:${scheme[key]}`)
    .join(';')
  return `${SHELL_SELECTOR}{${declarations}}`
}

export function useBrandSchemeStyle() {
  const { scheme } = storeToRefs(useBrandingStore())
  return computed(() => brandSchemeCss(scheme.value))
}
