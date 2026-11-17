import { defineStore } from 'pinia'
import { brandingService } from '@/services/brandingService'
import { DEFAULT_BRAND_COLORS, LOGO_SURFACES } from '@/constants/branding'
import { deriveBrandScheme, isBrandScheme } from '@/utils/brandingContrast'

const DEFAULT_SCHEME = deriveBrandScheme(DEFAULT_BRAND_COLORS)

function logosOf(source) {
  return Object.fromEntries(LOGO_SURFACES.map((surface) => [surface, source?.[surface] ?? null]))
}

function schemeOf(theme) {
  return isBrandScheme(theme?.scheme) ? theme.scheme : DEFAULT_SCHEME
}

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    scheme: DEFAULT_SCHEME,
    logos: logosOf(),
    status: 'idle',
  }),
  actions: {
    apply(theme) {
      this.scheme = schemeOf(theme)
      this.logos = logosOf(theme?.logos)
    },
    async load() {
      this.status = 'loading'
      try {
        const { data } = await brandingService.get()
        this.apply(data)
        this.status = 'success'
      } catch {
        this.status = 'error'
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle') await this.load()
    },
  },
})
