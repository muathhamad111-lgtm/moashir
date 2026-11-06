import { defineStore } from 'pinia'
import { kpisService } from '@/services/kpisService'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'

export const useFeaturedKpisStore = defineStore('featuredKpis', {
  state: () => ({ kpis: [], minimum: SITE_SETTINGS_LIMITS.featuredMin, status: 'idle' }),
  getters: {
    shown: (state) => (state.kpis.length >= state.minimum ? state.kpis : []),
  },
  actions: {
    async load() {
      this.status = 'loading'
      try {
        const envelope = await kpisService.getFeatured()
        this.kpis = Array.isArray(envelope.data) ? envelope.data : []
        this.minimum = envelope.meta?.minimum ?? SITE_SETTINGS_LIMITS.featuredMin
        this.status = 'success'
      } catch {
        this.kpis = []
        this.status = 'error'
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle') await this.load()
    },
  },
})
