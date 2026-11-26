import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { ndaService } from '@/services/ndaService'
import { markNdaAccepted } from '@/api/ndaEvents'

// The mandatory agreement modal (operator decision, 2026-11-22). It opens after sign-in when the session says an
// acceptance is due, or when the server refuses a request with `nda.acceptance_required`; it closes only on
// acceptance. The server gate is the real enforcement.
export const useNdaGateStore = defineStore('ndaGate', {
  state: () => ({
    open: false,
    status: 'idle',
    version: null,
    accepting: false,
    errorMessage: '',
  }),
  actions: {
    async show() {
      this.open = true
      await this.load()
    },
    async load() {
      this.status = 'loading'
      this.errorMessage = ''
      try {
        const { data } = await ndaService.mine()
        this.version = data.version
        this.status = data.version ? 'ready' : 'error'
      } catch (error) {
        this.errorMessage = error?.normalized?.message ?? ''
        this.status = 'error'
      }
    },
    async accept() {
      if (!this.version || this.accepting) return false
      this.accepting = true
      this.errorMessage = ''
      try {
        await ndaService.accept(this.version.id)
        useAuthStore().patchUser({ ndaAcceptanceRequired: false })
        this.open = false
        markNdaAccepted()
        return true
      } catch (error) {
        this.errorMessage = error?.normalized?.message ?? ''
        if (error?.normalized?.code === 'nda.version_not_current') await this.load()
        return false
      } finally {
        this.accepting = false
      }
    },
  },
})
