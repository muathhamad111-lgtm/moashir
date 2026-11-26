import { defineStore } from 'pinia'
import { contentService } from '@/services/contentService'

function toLocalized(raw) {
  return raw ? { ar: raw.ar ?? '', en: raw.en ?? '' } : null
}

// Descriptions of the assessment paths as set in «إدارة المسارات»; loaded once per session.
export const usePathIdentityStore = defineStore('pathIdentity', {
  state: () => ({ status: 'idle', byPath: {} }),
  actions: {
    async load() {
      this.status = 'loading'
      try {
        const { data } = await contentService.listPathIdentities()
        this.byPath = Object.fromEntries(
          (data ?? []).map((item) => [item.path, { description: toLocalized(item.description) }]),
        )
        this.status = 'success'
      } catch {
        this.status = 'error'
      }
    },
    ensureLoaded() {
      if (this.status === 'idle' || this.status === 'error') return this.load()
      return Promise.resolve()
    },
    replace(path, identity) {
      this.byPath = { ...this.byPath, [path]: identity }
    },
  },
})
