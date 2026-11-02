import { apiClient } from '@/api/client'
import { API_BASE_URL } from '@/api/config'
import { withJsonErrorBody } from './mappers/blobError'
import { fileNameFrom } from './mappers/download'
import { serviceRequest } from './serviceRequest'

const LINK_REJECTIONS = [403, 409, 410]
const DOWNLOAD_PATH = /^\/files\/[0-9a-f-]{36}\/download$/i

export class UntrustedDownloadLink extends Error {
  constructor() {
    super('untrusted_download_link')
    this.name = 'UntrustedDownloadLink'
  }
}

function apiPathOf(url) {
  const parsed = new URL(url, window.location.origin)
  const base = new URL(API_BASE_URL, window.location.origin)
  if (parsed.origin !== base.origin || !parsed.pathname.startsWith(base.pathname)) throw new UntrustedDownloadLink()
  const path = parsed.pathname.slice(base.pathname.length)
  if (!DOWNLOAD_PATH.test(path)) throw new UntrustedDownloadLink()
  return `${path}${parsed.search}`
}

export const filesService = {
  fetchSignedFile(signedDownload) {
    const execute = async () => {
      const response = await withJsonErrorBody(apiClient.get(apiPathOf(signedDownload.url), { responseType: 'blob' }))
      return { data: { blob: response.data, fileName: fileNameFrom(response.headers, signedDownload.fileName) } }
    }
    return serviceRequest('files.fetchSignedFile', execute, { entity: 'stored-file' }, { expectedStatuses: LINK_REJECTIONS })
  },
}
