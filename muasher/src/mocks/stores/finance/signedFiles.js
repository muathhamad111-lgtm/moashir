import { HttpResponse } from 'msw'
import { financeState, findFile, persistFinanceState } from './financeState'
import { financeReject } from './financeRejection'
import { nowIso } from './paymentLifecycle'

export const SIGNED_LINK_MINUTES = 5

const fileContents = new Map()

function signatureOf(fileId, expires, userId) {
  let hash = 2166136261
  for (const character of `${fileId}|${expires}|${userId}`) {
    hash ^= character.charCodeAt(0)
    hash = Math.imul(hash, 16777619) >>> 0
  }
  return hash.toString(16).padStart(8, '0').repeat(8)
}

export function storeFile({
  purpose,
  name,
  mimeType,
  sizeBytes,
  scanStatus = 'clean',
  content = null,
}) {
  const file = {
    id: crypto.randomUUID(),
    purpose,
    name,
    mimeType,
    sizeBytes,
    scanStatus,
    createdAt: nowIso(),
  }
  financeState().files.push(file)
  if (content !== null) fileContents.set(file.id, content)
  persistFinanceState()
  return file
}

export function signedDownloadOf(user, file) {
  const expiresAt = new Date(Date.now() + SIGNED_LINK_MINUTES * 60000)
  const expires = Math.floor(expiresAt.getTime() / 1000)
  const query = new URLSearchParams({
    expires: String(expires),
    signature: signatureOf(file.id, expires, user.id),
    user: String(user.id),
  })
  return {
    url: `${window.location.origin}/api/v1/files/${file.id}/download?${query}`,
    expiresAt: expiresAt.toISOString(),
    fileName: file.name,
  }
}

function placeholderContent(file) {
  if (file.mimeType === 'text/csv') return '\uFEFFreference,status\n'
  if (file.mimeType === 'application/pdf') return `%PDF-1.4\n% ${file.name}\n%%EOF\n`
  return new Uint8Array([0x89, 0x50, 0x4e, 0x47])
}

function verifiedLink(url, user) {
  const expires = Number(url.searchParams.get('expires'))
  const fileId = url.pathname.split('/').at(-2)
  const valid =
    url.searchParams.get('signature') === signatureOf(fileId, expires, url.searchParams.get('user'))
  if (!valid || url.searchParams.get('user') !== String(user.id))
    throw financeReject.signatureInvalid()
  if (expires * 1000 < Date.now()) throw financeReject.signatureExpired()
  return fileId
}

export function fileDownloadResponse(request, user) {
  const file = findFile(verifiedLink(new URL(request.url), user))
  if (!file) throw financeReject.notFound()
  if (file.scanStatus !== 'clean') throw financeReject.fileNotClean()
  const body = fileContents.get(file.id) ?? placeholderContent(file)
  return new HttpResponse(body, {
    status: 200,
    headers: {
      'Content-Type': file.mimeType,
      'Content-Disposition': `attachment; filename="${file.name}"`,
      'Cache-Control': 'no-store',
      'X-Content-Type-Options': 'nosniff',
    },
  })
}
