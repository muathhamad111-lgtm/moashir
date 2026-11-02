const FILE_NAME_PATTERN = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i

export function fileNameFrom(headers, fallback) {
  const disposition = headers?.['content-disposition'] ?? ''
  const match = FILE_NAME_PATTERN.exec(disposition)
  return match ? decodeURIComponent(match[1]) : fallback
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
