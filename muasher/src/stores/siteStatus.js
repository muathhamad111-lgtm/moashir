import { defineStore } from 'pinia'
import { contentService } from '@/services/contentService'

const MAX_ATTEMPTS = 2

async function fetchMaintenanceFlag() {
  let lastError = null
  for (let attempt = 0; attempt < MAX_ATTEMPTS; attempt += 1) {
    try {
      const { data } = await contentService.getLanding()
      return Boolean(data?.maintenance)
    } catch (error) {
      lastError = error
    }
  }
  throw lastError
}

export const useSiteStatusStore = defineStore('siteStatus', {
  state: () => ({
    maintenance: false,
    status: 'idle',
  }),
  actions: {
    async refresh() {
      this.status = 'loading'
      try {
        this.maintenance = await fetchMaintenanceFlag()
        this.status = 'success'
      } catch {
        this.maintenance = false
        this.status = 'error'
      }
      return this.maintenance
    },
    async ensureChecked() {
      if (this.status === 'idle') await this.refresh()
      return this.maintenance
    },
    markPublished(published) {
      this.maintenance = !published
      this.status = 'success'
    },
  },
})
