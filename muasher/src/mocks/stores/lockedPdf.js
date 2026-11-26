// The API asks qpdf whether a PDF needs a password to open. The mock only looks for an encryption
// dictionary, so here a PDF that merely restricts printing or copying is refused as well.
const PDF_SIGNATURE = '%PDF-'
const ENCRYPTION_KEY = '/Encrypt'

export function isLockedPdf(bytes) {
  const text = new TextDecoder('windows-1252').decode(bytes)
  return text.startsWith(PDF_SIGNATURE) && text.includes(ENCRYPTION_KEY)
}
