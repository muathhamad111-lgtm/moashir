import { defineStore } from 'pinia'
import { brandingService } from '@/services/brandingService'
import { DEFAULT_ACCENT_TOKEN, isKnownAccent } from '@/constants/branding'

function emptyLogos() {
  return { dark: null, light: null }
}

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    accentToken: DEFAULT_ACCENT_TOKEN,
    logos: emptyLogos(),
    status: 'idle',
  }),
  actions: {
    apply(theme) {
      this.accentToken = isKnownAccent(theme?.accentToken) ? theme.accentToken : DEFAULT_ACCENT_TOKEN
      this.logos = { ...emptyLogos(), ...theme?.logos }
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
