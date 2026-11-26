// RFC 6266: when both are sent, `filename*` (RFC 5987, UTF-8) carries the real name and
// `filename` is only an ASCII fallback for old clients (the API sends `download.pdf`).
const ENCODED_NAME_PATTERN = /filename\*\s*=\s*[\w-]+'[^']*'([^;\s]+)/i
const PLAIN_NAME_PATTERN = /(?:^|;)\s*filename\s*=\s*(?:"([^"]*)"|([^;\s]+))/i

function encodedNameOf(disposition) {
  const match = ENCODED_NAME_PATTERN.exec(disposition)
  if (!match) return ''
  try {
    return decodeURIComponent(match[1])
  } catch {
    return ''
  }
}

function plainNameOf(disposition) {
  const match = PLAIN_NAME_PATTERN.exec(disposition)
  return match ? (match[1] ?? match[2]).trim() : ''
}

export function fileNameFrom(headers, fallback) {
  const disposition = headers?.['content-disposition'] ?? ''
  return encodedNameOf(disposition) || plainNameOf(disposition) || fallback
}

export function toDownload(response, fallbackName) {
  return {
    data: {
      blob: response.data,
      fileName: fileNameFrom(response.headers, fallbackName),
      rowCount: Number(response.headers?.['x-export-row-count'] ?? 0),
    },
  }
}
