import { defineStore } from 'pinia'
import { contentService } from '@/services/contentService'
import { useSiteStatusStore } from './siteStatus'

function emptyState() {
  return {
    status: 'idle',
    title: null,
    seo: null,
    sections: [],
    siteSettings: null,
    publishedAt: null,
  }
}

export const usePublicHomeStore = defineStore('publicHome', {
  state: emptyState,
  actions: {
    apply(data) {
      this.title = data.title ?? null
      this.seo = data.seo ?? null
      this.sections = data.sections ?? []
      this.siteSettings = data.siteSettings ?? null
      this.publishedAt = data.publishedAt ?? null
      this.status = this.sections.length ? 'success' : 'empty'
    },
    async load() {
      this.status = 'loading'
      try {
        const { data } = await contentService.getHome()
        const published = Boolean(data?.published)
        useSiteStatusStore().markPublished(published)
        if (published) this.apply(data)
        else Object.assign(this, emptyState(), { status: 'empty' })
      } catch {
        this.status = 'error'
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle' || this.status === 'error') await this.load()
    },
    invalidate() {
      Object.assign(this, emptyState())
    },
  },
})
