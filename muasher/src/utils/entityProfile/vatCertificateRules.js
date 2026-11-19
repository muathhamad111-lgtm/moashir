import { VAT_CERTIFICATE_RULES } from '@/constants/entityProfile'

export function vatCertificateIssue(file) {
  if (!file) return 'required'
  const isPdf = VAT_CERTIFICATE_RULES.types.includes(file.type) || /\.pdf$/i.test(file.name ?? '')
  if (!isPdf) return 'type'
  if (file.size > VAT_CERTIFICATE_RULES.maxBytes) return 'size'
  return null
}
