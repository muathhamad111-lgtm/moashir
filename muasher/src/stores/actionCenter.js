import { defineStore } from 'pinia'
import { dashboardService } from '@/services/dashboardService'

export const useActionCenterStore = defineStore('actionCenter', {
  state: () => ({ items: [], status: 'idle', generatedAt: null }),
  getters: {
    total: (state) => state.items.length,
    hasItems: (state) => state.items.length > 0,
  },
  actions: {
    async load() {
      this.status = 'loading'
      try {
        const { data, generatedAt } = await dashboardService.getActionCenter()
        this.items = data
        this.generatedAt = generatedAt ?? null
        this.status = 'success'
      } catch {
        this.items = []
        this.status = 'error'
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle' || this.status === 'error') await this.load()
    },
    reset() {
      this.items = []
      this.status = 'idle'
      this.generatedAt = null
    },
  },
})
