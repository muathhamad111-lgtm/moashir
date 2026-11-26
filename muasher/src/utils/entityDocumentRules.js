import { ENTITY_DOCUMENT_RULES } from '@/constants/entityDocuments'

const PDF_NAME = /\.pdf$/i

export function entityDocumentIssue(file) {
  if (!file) return 'required'
  const isPdf = ENTITY_DOCUMENT_RULES.types.includes(file.type) || PDF_NAME.test(file.name ?? '')
  if (!isPdf) return 'type'
  if (file.size > ENTITY_DOCUMENT_RULES.maxBytes) return 'size'
  return null
}
