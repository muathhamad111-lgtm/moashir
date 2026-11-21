import { defineStore } from 'pinia'
import { dashboardService } from '@/services/dashboardService'

export const useEntityPathsStore = defineStore('entityPaths', {
  state: () => ({ board: null, status: 'idle' }),
  getters: {
    paths: (state) => state.board?.paths ?? null,
    nextStep: (state) => state.board?.nextStep ?? null,
    level: (state) => state.board?.level ?? null,
  },
  actions: {
    async load() {
      this.status = 'loading'
      try {
        const { data } = await dashboardService.getEntityBoard()
        this.board = data
        this.status = 'success'
      } catch (error) {
        this.status = 'error'
        throw error
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle' || this.status === 'error') await this.load().catch(() => null)
    },
    reset() {
      this.board = null
      this.status = 'idle'
    },
  },
})
