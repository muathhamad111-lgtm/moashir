import { defineStore } from 'pinia'
import { requestsService } from '@/services/requestsService'

const SINGLE_ROW = { perPage: 1 }

export const useRequestPathCountsStore = defineStore('requestPathCounts', {
  state: () => ({ ownerId: null, queue: [], assigned: [] }),
  actions: {
    claim(userId) {
      if (this.ownerId === userId) return
      this.ownerId = userId
      this.queue = []
      this.assigned = []
    },
    applyQueue(counts) {
      this.queue = [...(counts ?? [])]
    },
    applyAssigned(counts) {
      this.assigned = [...(counts ?? [])]
    },
    async refreshQueue() {
      const envelope = await requestsService.listQueue(SINGLE_ROW).catch(() => null)
      if (envelope) this.applyQueue(envelope.meta.pathCounts)
    },
    async refreshAssigned() {
      const envelope = await requestsService.listAssigned(SINGLE_ROW).catch(() => null)
      if (envelope) this.applyAssigned(envelope.meta.pathCounts)
    },
  },
})
