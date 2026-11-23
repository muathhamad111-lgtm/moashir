import { defineStore } from 'pinia'
import { certificatesService } from '@/services/certificatesService'
import { accreditedService } from '@/services/accreditedService'
import { LEVEL_CERTIFICATE_STATUS } from '@/constants/levels'
import { currentLevelCertificate } from '@/utils/levelTier'

// The level comes from accredited certificates only; award certificates carry no level.
const CERTIFICATE_QUERY = {
  'filter[status]': LEVEL_CERTIFICATE_STATUS,
  'filter[kind]': 'accredited',
  perPage: 1,
}

export const useEntityLevelStore = defineStore('entityLevel', {
  state: () => ({ certificate: null, accredited: null, status: 'idle' }),
  actions: {
    async load() {
      this.status = 'loading'
      try {
        const [certificates, current] = await Promise.all([
          certificatesService.listCards(CERTIFICATE_QUERY),
          accreditedService.getCurrent(),
        ])
        this.certificate = currentLevelCertificate(certificates.data)
        this.accredited = current.data
        this.status = 'success'
      } catch {
        this.status = 'error'
      }
    },
    async ensureLoaded() {
      if (this.status === 'idle' || this.status === 'error') await this.load()
    },
    reset() {
      this.certificate = null
      this.accredited = null
      this.status = 'idle'
    },
  },
})
