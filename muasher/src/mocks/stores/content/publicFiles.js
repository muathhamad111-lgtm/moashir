import { fixtureImageAssets, publicFileUrl } from '../../fixtures/cmsImages'

const SIGNATURES = Object.freeze({
  'image/png': [0x89, 0x50, 0x4e, 0x47],
  'image/jpeg': [0xff, 0xd8, 0xff],
})

const SVG_HEAD = /^\s*(<\?xml[^>]*>\s*)?(<!--[\s\S]*?-->\s*)*<svg[\s>]/i

const staticAssets = new Map(
  Object.entries(fixtureImageAssets).map(([path, fileId]) => [fileId, path]),
)
const uploads = new Map()

function newFileId() {
  return globalThis.crypto.randomUUID()
}

async function headOf(file, length) {
  return new Uint8Array(await file.slice(0, length).arrayBuffer())
}

export async function hasMatchingContent(file) {
  if (file.type === 'image/svg+xml') return SVG_HEAD.test(await file.slice(0, 512).text())
  const signature = SIGNATURES[file.type]
  if (!signature) return false
  const head = await headOf(file, signature.length)
  return signature.every((byte, index) => head[index] === byte)
}

export function registerUpload(file, purpose) {
  const fileId = newFileId()
  uploads.set(fileId, { blob: file, mimeType: file.type, purpose })
  return { fileId, url: publicFileUrl(fileId), mimeType: file.type, sizeBytes: file.size }
}

export function isUsableFile(fileId, purpose) {
  if (staticAssets.has(fileId)) return true
  return uploads.get(fileId)?.purpose === purpose
}

export function imageOf(fileId) {
  return fileId ? { fileId, url: publicFileUrl(fileId) } : null
}

export function publicFileSource(fileId) {
  if (uploads.has(fileId)) return { kind: 'blob', ...uploads.get(fileId) }
  if (staticAssets.has(fileId)) return { kind: 'static', path: staticAssets.get(fileId) }
  return null
}

export function resetPublicFiles() {
  uploads.clear()
}
