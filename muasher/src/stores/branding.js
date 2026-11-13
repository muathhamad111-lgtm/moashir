import { defineStore } from 'pinia'
import { brandingService } from '@/services/brandingService'
import { DEFAULT_ACCENT_TOKEN, LOGO_SURFACES, isKnownAccent } from '@/constants/branding'

function logosOf(source) {
  return Object.fromEntries(LOGO_SURFACES.map((surface) => [surface, source?.[surface] ?? null]))
}

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    accentToken: DEFAULT_ACCENT_TOKEN,
    logos: logosOf(),
    status: 'idle',
  }),
  actions: {
    apply(theme) {
      this.accentToken = isKnownAccent(theme?.accentToken)
        ? theme.accentToken
        : DEFAULT_ACCENT_TOKEN
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
