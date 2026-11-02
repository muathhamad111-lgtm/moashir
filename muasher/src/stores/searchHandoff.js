import { defineStore } from 'pinia'

export const useSearchHandoffStore = defineStore('searchHandoff', {
  state: () => ({
    pending: {},
  }),
  actions: {
    hand(routeName, text) {
      if (!routeName || !text) return
      this.pending = { ...this.pending, [routeName]: text }
    },
    consume(routeName) {
      const text = this.pending[routeName] ?? ''
      if (text) {
        const next = { ...this.pending }
        delete next[routeName]
        this.pending = next
      }
      return text
    },
  },
})
