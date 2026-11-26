import { HttpResponse, bypass } from 'msw'
import { http } from '../contract/http'
import { publicFileSource } from '../stores/content/publicFiles'

function staticResponse(path) {
  return fetch(bypass(new URL(path, globalThis.location.origin)))
}

export const publicFilesHandlers = [
  http.get('/api/v1/content/files/:asset', ({ params }) => {
    const source = publicFileSource(params.asset)
    if (!source) return new HttpResponse(null, { status: 404 })
    if (source.kind === 'static') return staticResponse(source.path)
    return new HttpResponse(source.blob, { headers: { 'Content-Type': source.mimeType } })
  }),
]
