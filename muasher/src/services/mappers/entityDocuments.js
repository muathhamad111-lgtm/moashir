import { PDF_MIME_TYPE, STAFF_ENTITY_DOCUMENTS } from '@/constants/entityDocuments'

export function toEntityDocument(raw) {
  if (!raw?.id) return null
  return {
    id: raw.id,
    name: raw.name ?? '',
    mimeType: raw.mimeType ?? PDF_MIME_TYPE,
    sizeBytes: Number(raw.sizeBytes ?? 0),
    uploadedAt: raw.uploadedAt ?? null,
  }
}

export function toEntityDocuments(raw) {
  return Object.fromEntries(
    STAFF_ENTITY_DOCUMENTS.map((document) => [document, toEntityDocument(raw?.[document])]),
  )
}
