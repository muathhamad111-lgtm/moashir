import { defineStore } from 'pinia'
import { legalService } from '@/services/legalService'
import { contentService } from '@/services/contentService'
import { toLegalDocument, toLegalPage } from '@/services/mappers/legal'
import { LEGAL_KINDS, LEGAL_STATES } from '@/constants/legal'

const NOT_PUBLISHED = 404

function fetchDocument(kind) {
  if (kind === LEGAL_KINDS.ACCESSIBILITY)
    return contentService.getPage(kind).then(({ data }) => toLegalPage(data))
  return legalService.getCurrent(kind).then(({ data }) => toLegalDocument(data))
}

export const useLegalDocumentsStore = defineStore('legalDocuments', {
  state: () => ({ states: {}, documents: {}, pending: {} }),
  getters: {
    isAvailable: (state) => (kind) => state.states[kind] === LEGAL_STATES.AVAILABLE,
    stateOf: (state) => (kind) => state.states[kind] ?? LEGAL_STATES.LOADING,
  },
  actions: {
    async fetch(kind) {
      this.states[kind] = LEGAL_STATES.LOADING
      try {
        this.documents[kind] = await fetchDocument(kind)
        this.states[kind] = LEGAL_STATES.AVAILABLE
      } catch (error) {
        this.states[kind] =
          error?.normalized?.status === NOT_PUBLISHED ? LEGAL_STATES.MISSING : LEGAL_STATES.ERROR
      }
    },
    load(kind, { force = false } = {}) {
      const known = [LEGAL_STATES.AVAILABLE, LEGAL_STATES.MISSING].includes(this.states[kind])
      if (known && !force) return Promise.resolve()
      if (!this.pending[kind] || force)
        this.pending[kind] = this.fetch(kind).finally(() => {
          delete this.pending[kind]
        })
      return this.pending[kind]
    },
    loadAll(kinds = Object.values(LEGAL_KINDS)) {
      return Promise.all(kinds.map((kind) => this.load(kind)))
    },
  },
})
