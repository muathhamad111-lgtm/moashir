import { defineStore } from 'pinia'
import { contentService } from '@/services/contentService'

export const useCertificateLevelsStore = defineStore('certificateLevels', {
  state: () => ({ levels: [], status: 'idle' }),
  actions: {
    async load() {
      this.status = 'loading'
      try {
        const envelope = await contentService.getCertificateLevels()
        this.levels = Array.isArray(envelope?.data?.levels) ? envelope.data.levels : []
        this.status = this.levels.length ? 'success' : 'empty'
      } catch {
        this.levels = []
        this.status = 'error'
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle' || this.status === 'error') await this.load()
    },
  },
})
